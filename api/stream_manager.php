<?php
/**
 * Stream Manager Script
 * This script handles starting and stopping FFmpeg processes for cameras.
 */

require_once __DIR__ . '/../core/config.php';

function isProcessRunning($name, $cmdLineSubstring) {
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'wmic process get Commandline';
        exec($cmd, $output);
        foreach ($output as $line) {
            if (str_contains($line, $cmdLineSubstring) && str_contains($line, 'ffmpeg')) {
                return true;
            }
        }
    } else {
        $output = [];
        $cmd = "ps aux | grep -v grep | grep " . escapeshellarg($cmdLineSubstring);
        exec($cmd, $output);
        return !empty($output);
    }
    return false;
}

function killStreamByCameraId($cameraId) {
    $marker = "cam_$cameraId";
    if (PHP_OS_FAMILY === 'Windows') {
        return; // handled by stream_control.php
    }
    // Kill any existing ffmpeg for this camera
    exec("pkill -f " . escapeshellarg("ffmpeg.*$marker") . " 2>/dev/null");
    // Wait a moment for process to die
    usleep(500000); // 0.5 second
}

function startStream($cameraId, $rtspUrl) {
    global $pdo;

    // IMPORTANT: Kill any existing stream for this camera first to prevent conflicts
    killStreamByCameraId($cameraId);

    // Check if recording is enabled for this camera
    $stmt = $pdo->prepare("SELECT is_recording FROM cameras WHERE id = ?");
    $stmt->execute([$cameraId]);
    $camData = $stmt->fetch();
    $isRecording = (bool)($camData['is_recording'] ?? false);

    $outputDir = STREAMS_DIR . "cam_$cameraId/";
    if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);
    
    // Clean up stale HLS files before starting
    array_map('unlink', glob($outputDir . "*.ts") ?: []);
    @unlink($outputDir . "index.m3u8");

    $hlsFile = $outputDir . "index.m3u8";
    $hlsSegmentPattern = $outputDir . "seg_%05d.ts";
    
    $ffmpegPath = FFMPEG_PATH;
    $camMarker = "cam_$cameraId";

    // Optimized FFmpeg command:
    // -c:v copy: passthrough video (no transcoding = low CPU, no quality loss)
    // -fflags nobuffer+genpts: reduces latency, generates proper timestamps
    // -probesize 1M: enough for 1080p stream analysis
    $cmd = "$ffmpegPath " .
           "-hide_banner -loglevel warning " .
           "-rtsp_transport tcp " .
           "-fflags nobuffer+genpts -flags low_delay " .
           "-probesize 1000000 -analyzeduration 1000000 " .
           "-i " . escapeshellarg($rtspUrl) . " " .
           "-metadata comment=" . escapeshellarg($camMarker) . " " .
           "-map 0:v:0 -map 0:a? -c:v copy -c:a aac -ar 44100 " .
           // Output #1: HLS for live view
           "-f hls -hls_time 2 -hls_list_size 5 -hls_flags delete_segments+independent_segments -hls_allow_cache 0 " .
           "-hls_segment_filename " . escapeshellarg($hlsSegmentPattern) . " " .
           escapeshellarg($hlsFile);

    // Output #2: segmented MP4 recording (Only if enabled)
    if ($isRecording) {
        $recordDir = RECORDINGS_DIR . "cam_$cameraId/";
        if (!is_dir($recordDir)) mkdir($recordDir, 0777, true);
        
        $cmd .= " -f segment -segment_time " . (int)SEGMENT_TIME . " " .
                "-segment_format mp4 " .
                "-segment_format_options movflags=+frag_keyframe+empty_moov " .
                "-reset_timestamps 1 -strftime 1 " .
                escapeshellarg("$recordDir%Y-%m-%d_%H-%M-%S.mp4");
    }
    
    if (PHP_OS_FAMILY === 'Windows') {
        pclose(popen("start \"\" /B " . $cmd, "r"));
    } else {
        // Log FFmpeg errors to a file for debugging in Docker (append mode)
        $logFile = '/var/www/html/ffmpeg_error.log';
        @touch($logFile);
        @chmod($logFile, 0666);
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "\n[$timestamp] Starting stream for cam_$cameraId\n", FILE_APPEND);
        exec("nohup " . $cmd . " >> " . escapeshellarg($logFile) . " 2>&1 &");
    }
    
    // Update camera status to online
    $pdo->prepare("UPDATE cameras SET status='online' WHERE id=?")->execute([$cameraId]);
}

// Logic to check all cameras and restart streams if they are dead (only if run directly)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $cameras = $pdo->query("SELECT * FROM cameras")->fetchAll();

    foreach ($cameras as $cam) {
        $camId = $cam['id'];
        $rtsp = $cam['rtsp_url'];
        
        $isRunning = isProcessRunning('ffmpeg', "cam_$camId");
        
        if (!$isRunning) {
            echo "Starting stream for Camera $camId...\n";
            startStream($camId, $rtsp);
        } else {
            echo "Camera $camId is already running.\n";
        }
    }
}

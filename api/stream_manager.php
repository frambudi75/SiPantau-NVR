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
        $cmd = "ps aux | grep -v grep | grep " . escapeshellarg($cmdLineSubstring);
        exec($cmd, $output);
        return !empty($output);
    }
    return false;
}

function startStream($cameraId, $rtspUrl) {
    global $pdo;

    $outputDir = STREAMS_DIR . "cam_$cameraId/";
    if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

    $hlsFile = $outputDir . "index.m3u8";
    $hlsSegmentPattern = $outputDir . "seg_%05d.ts";
    
    // FFmpeg command for HLS (Live View) and Segment Recording
    // -i [RTSP] Input
    // -c:v copy (Direct Stream Copy - very low CPU usage)
    // -f hls (HLS output)
    // -hls_time 2 (2 second segments)
    // -hls_list_size 5 (keep 5 segments in manifest)
    // -hls_flags delete_segments (auto delete old segments)
    
    // For recording, we'll use a separate process or dual output
    // For simplicity, let's start with Live View only first.
    
    $ffmpegPath = FFMPEG_PATH;
    $recordDir = RECORDINGS_DIR . "cam_$cameraId/";
    if (!is_dir($recordDir)) mkdir($recordDir, 0777, true);

    // FFmpeg Dual Output:
    // 1. HLS for Live View
    // 2. MP4 Segments for Recording (10-minute chunks)
    // Note: we embed a "cam_{id}" marker in the command line so process detection works.
    $camMarker = "cam_$cameraId";

    // Build command in a way that's friendly to Windows 'start' quoting rules.
    $cmd = "\"$ffmpegPath\" " .
           "-hide_banner -loglevel warning " .
           "-rtsp_transport tcp -stimeout 10000000 " .
           "-i \"$rtspUrl\" " .
           "-metadata comment=\"$camMarker\" " .
           "-map 0:v:0 -map 0:a? -c copy " .
           // Output #1: HLS for live view
           "-f hls -hls_time 2 -hls_list_size 5 -hls_flags delete_segments " .
           "-hls_segment_filename \"$hlsSegmentPattern\" " .
           "\"$hlsFile\" " .
           // Output #2: segmented MP4 recording
           "-f segment -segment_time " . (int)SEGMENT_TIME . " " .
           "-segment_format mp4 " .
           "-segment_format_options movflags=+frag_keyframe+empty_moov " .
           "-reset_timestamps 1 -strftime 1 " .
           "\"$recordDir%Y-%m-%d_%H-%M-%S.mp4\"";
    
    if (PHP_OS_FAMILY === 'Windows') {
        // Run in background on Windows.
        // Important: first quoted arg after `start` is treated as window title, so pass an empty title.
        pclose(popen("start \"\" /B " . $cmd, "r"));
    } else {
        exec($cmd . " > /dev/null 2>&1 &");
    }

    $pdo->prepare("UPDATE cameras SET status = 'online' WHERE id = ?")->execute([$cameraId]);
}

// Logic to check all cameras and restart streams if they are dead
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

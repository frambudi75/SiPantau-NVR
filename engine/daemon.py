#!/usr/bin/env python3
import subprocess
import time
import json
import os
import signal
import sys
import glob

# SiPantau NVR - High Precision Python Stream Daemon
# Replaces the cron-based PHP watchdog for millisecond-accuracy stream recovery.

FFMPEG_PATH = "ffmpeg"
WEB_ROOT = "/var/www/html"
STREAMS_DIR = os.path.join(WEB_ROOT, "streams")
RECORDINGS_DIR = os.path.join(WEB_ROOT, "recordings")

# Active subprocesses { camera_id: Popen_object }
active_processes = {}

def log(msg):
    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] {msg}", flush=True)

def get_config():
    """Fetch camera config and settings via local PHP execution"""
    php_code = """
    require '/var/www/html/core/config.php';
    $cams = $pdo->query('SELECT id, rtsp_url, is_recording FROM cameras')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'cameras' => $cams,
        'segment_time' => SEGMENT_TIME
    ]);
    """
    try:
        result = subprocess.run(["php", "-r", php_code], capture_output=True, text=True)
        if result.returncode == 0 and result.stdout:
            return json.loads(result.stdout)
    except Exception as e:
        log(f"Error fetching config: {e}")
    return {'cameras': [], 'segment_time': 600}

def cleanup_hls(cam_id):
    """Clean up old HLS files before starting a new stream"""
    cam_dir = os.path.join(STREAMS_DIR, f"cam_{cam_id}")
    os.makedirs(cam_dir, exist_ok=True)
    
    # Delete old .ts and .m3u8 files
    for f in glob.glob(os.path.join(cam_dir, "*.ts")):
        try: os.remove(f)
        except: pass
    try: os.remove(os.path.join(cam_dir, "index.m3u8"))
    except: pass

def update_camera_status(cam_id, status):
    php_code = f"""
    require '/var/www/html/core/config.php';
    $pdo->prepare('UPDATE cameras SET status=? WHERE id=?')->execute(['{status}', {cam_id}]);
    """
    subprocess.run(["php", "-r", php_code])

def start_stream(cam, segment_time):
    cam_id = cam['id']
    rtsp_url = cam['rtsp_url']
    is_recording = bool(int(cam['is_recording']))
    
    log(f"Starting stream for Camera {cam_id} with segment time {segment_time}s...")
    cleanup_hls(cam_id)
    
    cam_streams_dir = os.path.join(STREAMS_DIR, f"cam_{cam_id}")
    hls_file = os.path.join(cam_streams_dir, "index.m3u8")
    hls_pattern = os.path.join(cam_streams_dir, "seg_%05d.ts")
    
    cmd = [
        FFMPEG_PATH,
        "-hide_banner", "-loglevel", "warning",
        "-rtsp_transport", "tcp",
        "-fflags", "nobuffer+genpts", "-flags", "low_delay",
        "-probesize", "1000000", "-analyzeduration", "1000000",
        "-i", rtsp_url,
        "-metadata", f"comment=cam_{cam_id}",
        "-map", "0:v:0", "-map", "0:a?", "-c:v", "copy", "-c:a", "aac", "-ar", "44100",
        "-f", "hls", "-hls_time", "2", "-hls_list_size", "5",
        "-hls_flags", "delete_segments+independent_segments", "-hls_allow_cache", "0",
        "-hls_segment_filename", hls_pattern,
        hls_file
    ]
    
    if is_recording:
        record_dir = os.path.join(RECORDINGS_DIR, f"cam_{cam_id}")
        os.makedirs(record_dir, exist_ok=True)
        # Using faststart for MP4 ensures instantaneous playback on the web without stuttering
        rec_pattern = os.path.join(record_dir, "%Y-%m-%d_%H-%M-%S.mp4")
        cmd.extend([
            "-map", "0:v:0", "-map", "0:a?", "-c:v", "copy", "-c:a", "aac", "-ar", "44100",
            "-f", "segment", "-segment_time", str(segment_time),
            "-segment_format", "mp4",
            "-segment_format_options", "movflags=+faststart",
            "-reset_timestamps", "1", "-strftime", "1",
            rec_pattern
        ])
    
    # Start the process
    # Log FFmpeg output to file
    with open('/var/www/html/ffmpeg_error.log', 'a') as f:
        f.write(f"\n[{time.strftime('%Y-%m-%d %H:%M:%S')}] --- Python Daemon Starting Camera {cam_id} ---\n")
        f.flush()
        process = subprocess.Popen(cmd, stdout=f, stderr=f)
        active_processes[cam_id] = process
        
    update_camera_status(cam_id, 'online')

def stop_all_streams(signum=None, frame=None):
    log("Shutting down NVR Daemon. Terminating all streams...")
    for cam_id, process in active_processes.items():
        try:
            process.terminate()
            process.wait(timeout=2)
        except:
            process.kill()
        update_camera_status(cam_id, 'offline')
    sys.exit(0)

# Register shutdown signals
signal.signal(signal.SIGINT, stop_all_streams)
signal.signal(signal.SIGTERM, stop_all_streams)

def main():
    log("SiPantau NVR Daemon Started.")
    
    while True:
        try:
            config_data = get_config()
            cameras = config_data.get('cameras', [])
            segment_time = config_data.get('segment_time', 600)
            
            current_cam_ids = {cam['id'] for cam in cameras}
            
            # 1. Start or Restart Streams
            for cam in cameras:
                cam_id = cam['id']
                process = active_processes.get(cam_id)
                
                if process is None:
                    # New stream
                    start_stream(cam, segment_time)
                elif process.poll() is not None:
                    # Process died, restart it instantly!
                    log(f"Camera {cam_id} process died (exit code {process.returncode}). Restarting immediately.")
                    update_camera_status(cam_id, 'offline')
                    start_stream(cam, segment_time)
            
            # 2. Stop streams that were deleted from DB
            deleted_cams = list(set(active_processes.keys()) - current_cam_ids)
            for cam_id in deleted_cams:
                log(f"Camera {cam_id} removed from DB. Stopping stream.")
                proc = active_processes.pop(cam_id)
                try: proc.terminate()
                except: pass
                
        except Exception as e:
            log(f"Daemon Error: {e}")
            
        # Check every 2 seconds (High Precision)
        time.sleep(2)

if __name__ == "__main__":
    main()

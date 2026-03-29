<?php
namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\SyncingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct(protected SyncingService $syncService) {}

   public function downloadArchive($id, Request $request)
{
    $request->validate([
        'start'    => 'required|integer',
        'duration' => 'required|integer|max:3600',
    ]);

    $channel = Channel::where('external_id', $id)->firstOrFail();

    \Log::info('Download started', [
        'channel_id' => $id,
        'start'      => $request->start,
        'duration'   => $request->duration,
    ]);

    $m3u8Url = $this->syncService->getDownloadUrl($id, $request->start);

    if (!$m3u8Url) {
        \Log::error('Download failed: could not get m3u8 URL', ['channel_id' => $id]);
        return response()->json(['message' => 'Source stream unavailable'], 404);
    }

    \Log::info('m3u8 URL resolved', ['url' => $m3u8Url]);

    $duration = (int) $request->duration;
    $fileName = "archive_{$id}_{$request->start}.mp4";
    $tmpFile  = "/tmp/{$fileName}";

    $cmd = "ffmpeg"
        . " -protocol_whitelist file,http,https,tcp,tls,crypto"
        . " -i " . escapeshellarg($m3u8Url)
        . " -t {$duration}"
        . " -c copy"
        . " -f mp4"
        . " " . escapeshellarg($tmpFile)
        . " -y 2>/tmp/ffmpeg_last.log";

    \Log::info('Running ffmpeg', ['cmd' => $cmd]);

    exec($cmd, $output, $exitCode);

    $ffmpegLog = file_exists('/tmp/ffmpeg_last.log')
        ? file_get_contents('/tmp/ffmpeg_last.log')
        : '(no log file)';

    if ($exitCode !== 0) {
        \Log::error('ffmpeg exited with error', [
            'exit_code' => $exitCode,
            'ffmpeg_log' => $ffmpegLog,
        ]);
        return response()->json(['message' => 'Encoding failed'], 500);
    }

    if (!file_exists($tmpFile)) {
        \Log::error('ffmpeg succeeded but output file missing', [
            'expected_path' => $tmpFile,
            'ffmpeg_log'    => $ffmpegLog,
        ]);
        return response()->json(['message' => 'Encoding failed'], 500);
    }

    $fileSize = filesize($tmpFile);

    if ($fileSize < 1000) {
        \Log::error('ffmpeg output file too small', [
            'size_bytes' => $fileSize,
            'ffmpeg_log' => $ffmpegLog,
        ]);
        return response()->json(['message' => 'Encoding failed'], 500);
    }

    \Log::info('File written successfully', [
        'path'       => $tmpFile,
        'size_bytes' => $fileSize,
        'size_mb'    => round($fileSize / 1048576, 2),
    ]);

    return response()->download($tmpFile, $fileName, [
        'Content-Type'   => 'video/mp4',
        'Content-Length' => $fileSize,
    ])->deleteFileAfterSend(true);
}
}
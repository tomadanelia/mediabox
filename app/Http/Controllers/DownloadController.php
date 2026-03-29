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

    $m3u8Url = $this->syncService->getDownloadUrl($id, $request->start);

    if (!$m3u8Url) {
        return response()->json(['message' => 'Source stream unavailable'], 404);
    }


    $duration = (int) $request->duration;
    $fileName = "archive_{$id}_{$request->start}.mp4";
    $tmpFile  = "/tmp/{$fileName}";

    $cmd = "ffmpeg"
        . " -protocol_whitelist file,http,https,tcp,tls,crypto"
        . " -i " . escapeshellarg($m3u8Url)
        . " -t {$duration}"
        . " -map 0:2 -map 0:3"
        . " -c copy"
        . " -f mp4"
        . " " . escapeshellarg($tmpFile)
        . " -y 2>/tmp/ffmpeg_last.log";

    exec($cmd, $output, $exitCode);

    $ffmpegLog = file_exists('/tmp/ffmpeg_last.log')
        ? file_get_contents('/tmp/ffmpeg_last.log')
        : '(no log file)';

    if ($exitCode !== 0) {
        return response()->json(['message' => 'Encoding failed'], 500);
    }

    if (!file_exists($tmpFile)) {
        return response()->json(['message' => 'Encoding failed'], 500);
    }

    $fileSize = filesize($tmpFile);

    if ($fileSize < 1000) {
        return response()->json(['message' => 'Encoding failed'], 500);
    }

    return response()->download($tmpFile, $fileName, [
        'Content-Type'   => 'video/mp4',
        'Content-Length' => $fileSize,
    ])->deleteFileAfterSend(true);
}
}
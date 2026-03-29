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
            'start' => 'required|integer', 
            'duration' => 'required|integer|max:3600',
        ]);

        $channel = Channel::where('external_id', $id)->firstOrFail();
        
        $m3u8Url = $this->syncService->getDownloadUrl($id, $request->start);

        if (!$m3u8Url) {
            return response()->json(['message' => 'Source stream unavailable'], 404);
        }

        $fileName = "archive_{$id}_{$request->start}.mp4";
        $duration = (int) $request->duration;

        return new StreamedResponse(function () use ($m3u8Url, $duration) {
            $cmd = "ffmpeg -i " . escapeshellarg($m3u8Url) . " -t {$duration} -c copy -f mp4 -movflags frag_keyframe+empty_moov pipe:1";

            $process = proc_open($cmd, [
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ], $pipes);

            if (is_resource($process)) {
                while (!feof($pipes[1])) {
                    echo fread($pipes[1], 8192);
                    flush();
                }
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
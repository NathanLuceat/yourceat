<?php

namespace App\Http\Controllers;

use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;

class ProjectReadmeController extends Controller
{
    public function __invoke(string $repo, GitHubService $github): JsonResponse
    {
        $readme = $github->projectReadme($repo);
        abort_if($readme === null, 404);

        return response()->json($readme, $readme['status'] === 'unavailable' ? 503 : 200);
    }
}

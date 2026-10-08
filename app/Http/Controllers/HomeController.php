<?php

namespace App\Http\Controllers;

use App\Models\WebbliotecaActivity;
use App\Services\GitHubService;
use App\Services\WebbliotecaService;

class HomeController extends Controller
{
    public function __invoke(GitHubService $github, WebbliotecaService $webblioteca)
    {
        // Atualiza o estado inicial para não depender apenas do polling.
        $webblioteca->sync();

        return view('home', [
            'profile' => $github->profile(),
            'projects' => $github->repos(),
            'commits' => $github->recentCommits(),
            'readme' => $github->profileReadmeHtml(),
            'activities' => WebbliotecaActivity::orderByDesc('external_id')->limit(6)->get(),
        ]);
    }
}

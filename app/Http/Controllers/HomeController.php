<?php

namespace App\Http\Controllers;

use App\Services\GitHubService;
use App\Services\WebbliotecaService;

class HomeController extends Controller
{
    public function __invoke(GitHubService $github, WebbliotecaService $webblioteca)
    {
        return view('home', [
            'profile'    => $github->profile(),
            'projects'   => $github->repos(),
            'commits'    => $github->recentCommits(),
            'readme'     => $github->profileReadmeHtml(),
            'activities' => $webblioteca->recentActivities(),
        ]);
    }
}
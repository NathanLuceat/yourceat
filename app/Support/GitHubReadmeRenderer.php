<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class GitHubReadmeRenderer
{
    public function render(string $markdown, string $owner, string $repo, string $branch, string $path): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($owner, $repo, $branch, $path): void {
            $walker = $event->getDocument()->walker();
            while ($event = $walker->next()) {
                if (! $event->isEntering()) {
                    continue;
                }
                $node = $event->getNode();
                if ($node instanceof Link || $node instanceof Image) {
                    $node->setUrl($this->resolve($node->getUrl(), $owner, $repo, $branch, $path, $node instanceof Image));
                } elseif ($node instanceof Heading) {
                    $node->setLevel(min(6, $node->getLevel() + 2));
                }
            }
        });

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }

    private function resolve(string $url, string $owner, string $repo, string $branch, string $path, bool $image): string
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url)) {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match)) {
            return in_array(strtolower($match[1]), $image ? ['http', 'https'] : ['http', 'https', 'mailto'], true) ? $url : '';
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return '';
        }
        $relativePath = $parts['path'] ?? '';
        $file = $relativePath === '' ? $path : (str_starts_with($relativePath, '/') ? $relativePath : dirname($path).'/'.$relativePath);
        $segments = [];
        foreach (explode('/', $file) as $segment) {
            $segment = rawurldecode($segment);
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = rawurlencode($segment);
            }
        }
        $base = $image ? 'https://raw.githubusercontent.com/' : 'https://github.com/';
        $base .= rawurlencode($owner).'/'.rawurlencode($repo).'/'.($image ? '' : 'blob/').rawurlencode($branch).'/';

        return $base.implode('/', $segments)
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}

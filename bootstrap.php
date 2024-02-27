<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use TightenCo\Jigsaw\Jigsaw;

/** @var \Illuminate\Container\Container $container */
/** @var \TightenCo\Jigsaw\Events\EventBus $events */
if (class_exists("Dotenv\Dotenv")) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

/*
 * You can run custom code at different stages of the build process by
 * listening to the 'beforeBuild', 'afterCollections', and 'afterBuild' events.
 *
 * For example:
 *
 * $events->beforeBuild(function (Jigsaw $jigsaw) {
 *     // Your code here
 * });
 */

$events->beforeBuild(function (Jigsaw $jigsaw) {
    $changelog = Http::get("https://raw.githubusercontent.com/julienbourdeau/debugbar/master/CHANGELOG.md")->body();

    $headers = <<<TEXT
---
extends: _layouts.changelog
section: content
slug: changelog
title: Changelog
subtitle: "What's new in Debugbar?"
seo_title: "Rails Debugbar Changelog"
seo_description: "All new features, improvements and fixes in Debugbar and more importantly: all the breaking changes!"
---
TEXT;

    $content = $headers ."\n\n". $changelog;

    File::put(__DIR__.'/source/changelog.blade.md', $content);
});

$events->afterBuild(function (Jigsaw $jigsaw) {
    exec('git checkout -- '.__DIR__.'/source/changelog.blade.md');
});

$events->beforeBuild(function (Jigsaw $jigsaw) {
    $files = glob(__DIR__.'/source/assets/debugbar/*.js');

    if (count($files) !== 1) {
        echo "\n\nToo many demo files found in source/assets/debugbar\n\n";
        exit(1);
    }

    $jigsaw->setConfig('debugbarAssets', [
        'js' => basename($files[0]),
    ]);
});

$events->afterCollections(function (Jigsaw $jigsaw) {
    global $docsToc; // YOLO

    $docsToc = $jigsaw->getCollection('docs')->map(function ($page) {
        return [
            'title' => $page->title,
            'section' => $page->toc_section,
            'url' => $page->getPath(),
            'disabled' => $page->disabled ?? false,
        ];
    })->values()->groupBy('section');
});

\Torchlight\Jigsaw\TorchlightExtension::make($container, $events)->boot();

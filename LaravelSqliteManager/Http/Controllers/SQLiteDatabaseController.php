<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response;

class SQLiteDatabaseController
{
    public function stylesheet(Filesystem $filesystem): Response
    {
        return response($filesystem->get(dirname(__DIR__, 3).'/resources/css/sqlite-manager.css'), 200, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => 'text/css; charset=UTF-8',
        ]);
    }
}

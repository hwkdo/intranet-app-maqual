<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Http\Controllers;

use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QualDateiController
{
    public function __invoke(Qual $qual, string $collection): BinaryFileResponse|Response
    {
        abort_unless(in_array($collection, ['anmeldebestatigung', 'zertifikat'], true), 404);

        $user = Auth::user();
        abort_unless($user && app(QualPolicy::class)->view($user, $qual), 403);

        $media = $qual->getFirstMedia($collection);
        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }
}

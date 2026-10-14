<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

use App\Models\Gvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaqualModels
{
    /**
     * @return class-string<Model>
     */
    public static function user(): string
    {
        return (string) config('intranet-app-maqual.user_model', User::class);
    }

    /**
     * @return class-string<Model>
     */
    public static function gvp(): string
    {
        return (string) config('intranet-app-maqual.gvp_model', Gvp::class);
    }

    /**
     * @return Builder<Model>
     */
    public static function userQuery(): Builder
    {
        return static::user()::query();
    }

    /**
     * @return Builder<Model>
     */
    public static function gvpQuery(): Builder
    {
        return static::gvp()::query();
    }
}

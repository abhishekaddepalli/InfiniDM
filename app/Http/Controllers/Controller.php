<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Standalone stand-in for the base controller WaDesk core provides.
 *
 * The twelve shared Instagram controllers are byte-identical in both modes and
 * every one of them extends this class, but the class itself lives in core —
 * it is not in the shared tree, and it cannot be: core owns
 * app/Http/Controllers/Controller.php, so shipping it in the addon archive
 * would trip assert_no_core_collisions() and refuse the build. Hence this copy
 * under standalone/, which build.py rebases onto app/ for the standalone zip
 * only.
 *
 * This is a deliberate mirror of core's version rather than the empty Laravel
 * 12 stock shape. Nothing in the shared tree calls either member today, so an
 * empty class would boot — but "today" is the problem. Shared controllers are
 * written against whatever App\Http\Controllers\Controller offers in the host,
 * and if that contract is narrower here than in WaDesk then the first shared
 * controller to reach for paginateCollection() ships green, passes review, and
 * fatals in standalone only. Keeping the two shapes in step means one source
 * tree really does behave the same in both modes, which is the entire point of
 * the split. Both members are plain Illuminate; the standalone app already
 * requires laravel/framework, so this adds no dependency.
 *
 * Any member added to core's copy belongs here too.
 */
abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Paginate an already-materialised set.
     *
     * The Instagram pages that use this are showing records assembled in PHP —
     * Graph API responses merged with local rows — so there is no query left to
     * put a LIMIT on by the time the list exists.
     *
     * `partial` is dropped from the generated links alongside `page` because it
     * is the flag the inbox uses to ask for a fragment instead of a full page;
     * letting it survive into a pagination URL would hand the visitor a bare
     * partial with no layout around it.
     */
    protected function paginateCollection(iterable $items, Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $collection = $items instanceof Collection ? $items->values() : collect($items)->values();
        $perPage = max(1, $perPage);
        $lastPage = max(1, (int) ceil($collection->count() / $perPage));
        $page = min(max(1, (int) $request->integer('page', 1)), $lastPage);

        return new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->except(['page', 'partial']),
            ]
        );
    }
}

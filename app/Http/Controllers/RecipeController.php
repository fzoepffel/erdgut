<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\EditorialContent;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Minimal recipe surface for the editorial-only v1 of ERD-354.
 *
 * Recipes live in {@see EditorialContent::recipe()} as a static rotation
 * keyed by slug. A real Recipe Eloquent model + admin CRUD lands in
 * ERD-358; this controller is intentionally thin and read-only so the
 * dashboard "Open the recipe →" link has somewhere to go in the meantime.
 */
final class RecipeController extends Controller
{
    public function show(string $slug): View
    {
        $recipe = EditorialContent::recipe($slug);
        if ($recipe === null) {
            throw new NotFoundHttpException;
        }

        return view('recipes.show', [
            'recipe' => $recipe,
        ]);
    }
}

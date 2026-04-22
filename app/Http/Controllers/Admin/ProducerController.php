<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProducerRequest;
use App\Http\Requests\Admin\UpdateProducerRequest;
use App\Models\Producer;
use App\Models\ProducerTranslation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProducerController extends Controller
{
    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return ['de', 'en', 'fr'];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Producer::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $locale = app()->getLocale();
        $query = Producer::query()
            ->select('producers.*')
            ->join('producer_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.producer_id', '=', 'producers.id')->where('pt.locale', '=', $locale);
            })
            ->with('translations')
            ->withCount('products');

        $search = isset($validated['q']) ? trim($validated['q']) : '';
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($escaped): void {
                $q->where('pt.name', 'like', '%'.$escaped.'%')
                    ->orWhere('pt.region', 'like', '%'.$escaped.'%');
            });
        }

        $producers = $query
            ->orderBy('pt.name')
            ->paginate(24)
            ->withQueryString();

        return view('admin.producers.index', [
            'producers' => $producers,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Producer::class);

        return view('admin.producers.create');
    }

    public function store(StoreProducerRequest $request): RedirectResponse
    {
        $this->authorize('create', Producer::class);

        $validated = $request->validated();
        $payload = $this->validatedProducerAttributes($validated, ['image', 'translations']);
        $translations = $validated['translations'];

        $producer = DB::transaction(function () use ($payload, $request): Producer {
            $producer = Producer::query()->create($payload);

            if ($request->hasFile('image')) {
                $path = $this->storeUploadedImage($request->file('image'), $producer);
                $producer->update(['image_path' => $path]);
            }

            return $producer->fresh();
        });

        DB::transaction(function () use ($producer, $translations): void {
            foreach ($this->locales() as $locale) {
                ProducerTranslation::query()->updateOrCreate(
                    ['producer_id' => $producer->id, 'locale' => $locale],
                    [
                        'name' => $translations[$locale]['name'],
                        'region' => $translations[$locale]['region'],
                        'description' => $translations[$locale]['description'],
                    ]
                );
            }
        });

        return redirect()
            ->route('app.admin.producers.edit', $producer)
            ->with('status', __('Producer created.'));
    }

    public function edit(Producer $producer): View
    {
        $this->authorize('update', $producer);

        return view('admin.producers.edit', [
            'producer' => $producer->load('translations'),
        ]);
    }

    public function update(UpdateProducerRequest $request, Producer $producer): RedirectResponse
    {
        $this->authorize('update', $producer);

        $validated = $request->validated();
        $payload = $this->validatedProducerAttributes($validated, ['image', 'remove_image', 'translations']);
        $translations = $validated['translations'];

        if ($request->boolean('remove_image')) {
            $this->deletePublicImage($producer->image_path);
            $payload['image_path'] = null;
        }

        DB::transaction(function () use ($request, $producer, $payload): void {
            $producer->update($payload);

            if ($request->hasFile('image')) {
                $this->deletePublicImage($producer->refresh()->image_path);
                $path = $this->storeUploadedImage($request->file('image'), $producer);
                $producer->update(['image_path' => $path]);
            }
        });

        DB::transaction(function () use ($producer, $translations): void {
            foreach ($this->locales() as $locale) {
                ProducerTranslation::query()->updateOrCreate(
                    ['producer_id' => $producer->id, 'locale' => $locale],
                    [
                        'name' => $translations[$locale]['name'],
                        'region' => $translations[$locale]['region'],
                        'description' => $translations[$locale]['description'],
                    ]
                );
            }
        });

        return redirect()
            ->route('app.admin.producers.edit', $producer)
            ->with('status', __('Producer updated.'));
    }

    public function destroy(Producer $producer): RedirectResponse
    {
        $this->authorize('delete', $producer);

        if ($producer->products()->exists()) {
            return redirect()
                ->route('app.admin.producers.index')
                ->with('error', __('This producer cannot be deleted while products are still assigned. Reassign or delete those products first.'));
        }

        $this->deletePublicImage($producer->image_path);
        $producer->delete();

        return redirect()
            ->route('app.admin.producers.index')
            ->with('status', __('Producer deleted.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $without
     * @return array<string, mixed>
     */
    private function validatedProducerAttributes(array $validated, array $without): array
    {
        foreach ($without as $key) {
            unset($validated[$key]);
        }

        return $validated;
    }

    private function storeUploadedImage(UploadedFile $file, Producer $producer): string
    {
        return Storage::disk('public')->putFile('producers/'.$producer->id, $file);
    }

    private function deletePublicImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}

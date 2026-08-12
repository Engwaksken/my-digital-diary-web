<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * JSON equivalent of the web app's CrudController — every tracking module
 * gets a matching Api\{Module}Controller by setting just $model and
 * $rules (no $fields/$icon/$accent here; those exist only to drive the
 * web app's generic Blade form/table, which the Flutter app doesn't use —
 * it builds its own native UI from the JSON shape instead).
 *
 * All 20 web modules now have their API equivalent built this way.
 */
abstract class ApiCrudController extends Controller
{
    protected string $model;
    protected array $rules = [];

    /**
     * ?archived=1 shows ONLY archived items (for a dedicated "Archived"
     * view); omitted or any other value shows only non-archived ones —
     * archived items never mix into the normal list by default, which
     * is the whole point of archiving something.
     */
    public function index(Request $request): JsonResponse
    {
        $showArchived = $request->boolean('archived');

        $items = $this->model::where('user_id', $request->user()->id)
            ->where('is_archived', $showArchived)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;

        $item = $this->model::create($data);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        $item->update($data);

        return response()->json($item);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        $item->update(['is_archived' => true]);

        return response()->json($item);
    }

    public function unarchive(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        $item->update(['is_archived' => false]);

        return response()->json($item);
    }

    /**
     * A breakdown by 'status', matching the same stat-card shape the
     * web app's own list pages already show for status-driven modules
     * (see e.g. PlanController::index()'s Pending/In progress/
     * Completed cards) — only meaningful for modules that HAVE a
     * status column, hence the schema check rather than assuming one.
     */
    public function stats(Request $request): JsonResponse
    {
        $table = (new $this->model)->getTable();
        $userId = $request->user()->id;
        $total = $this->model::where('user_id', $userId)->where('is_archived', false)->count();

        if (! Schema::hasColumn($table, 'status')) {
            return response()->json(['total' => $total, 'by_status' => null]);
        }

        $byStatus = $this->model::where('user_id', $userId)
            ->where('is_archived', false)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(['total' => $total, 'by_status' => $byStatus]);
    }

    /**
     * A generic table PDF — every column in $rules except user_id,
     * humanized into a header (e.g. "target_date" -> "Target Date").
     * Not as polished as a hand-built per-module PDF would be, but
     * covers all 20 modules sharing this base class at once rather
     * than needing 20 separate templates.
     */
    public function downloadPdf(Request $request)
    {
        $userId = $request->user()->id;
        $items = $this->model::where('user_id', $userId)->where('is_archived', false)->orderByDesc('id')->get();

        $columns = array_values(array_filter(array_keys($this->rules), fn ($field) => $field !== 'user_id'));
        $headers = array_map(fn ($field) => Str::headline($field), $columns);

        $moduleLabel = Str::headline(class_basename($this->model));

        $pdf = Pdf::loadView('reports.generic-module', [
            'moduleLabel' => $moduleLabel,
            'columns' => $columns,
            'headers' => $headers,
            'items' => $items,
            'generatedFor' => $request->user()->name,
        ]);

        return $pdf->download(Str::slug($moduleLabel) . '-report.pdf');
    }
}

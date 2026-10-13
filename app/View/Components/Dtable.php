<?php

namespace App\View\Components;

use Illuminate\View\Component;

/**
 * Server-side data table (replaces the prototype's table()): search, select filters, sortable headers,
 * pagination, row links, row actions and CSV export - all driven by GET query params namespaced by table id:
 *   ?{id}[q]=..&{id}[f][key]=..&{id}[sort]=col&{id}[dir]=desc&{id}[page]=2&{id}[per]=25
 *
 * <x-dtable id="cases" :cols="$cols" :rows="$rows" :filters="$filters" :row-link="fn($r)=>route(...)" :actions="fn($r)=>'<html>'" />
 *
 * cols:    [['k' => 'id', 't' => 'Case', 'cls' => 'r', 'r' => fn($row) => 'html (escape it!)', 's' => fn($row) => scalar for sort/search/csv, 'nosort' => true], ...]
 * filters: [['k' => 'status', 'label' => 'Status', 'get' => fn($row) => value, 'opts' => ['A','B']], ...]
 * rows:    list of arrays/objects.
 */
class Dtable extends Component
{
    public array $state;
    public array $page = [];
    public int $total = 0;
    public int $pages = 1;
    public string $csv = '';

    public function __construct(
        public string $id,
        public array $cols,
        public $rows,
        public array $filters = [],
        public $rowLink = null,
        public $actions = null,
        public string $empty = 'Nothing here yet',
        public string $emptyHint = 'Records will appear here as they are created.',
        public int $per = 10,
        public string $tools = '',
        public bool $export = true,
    ) {
        $q = (array) request()->query($id, []);
        $this->state = [
            'q' => trim((string) ($q['q'] ?? '')), 'f' => (array) ($q['f'] ?? []), 'sort' => $q['sort'] ?? null,
            'dir' => ($q['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc', 'page' => max(1, (int) ($q['page'] ?? 1)),
            'per' => in_array((int) ($q['per'] ?? 0), [10, 25, 50, 100], true) ? (int) $q['per'] : $per,
        ];
        $this->compute();
    }

    public function value(array|object $row, array $c)
    {
        if (isset($c['s'])) {
            return ($c['s'])($row);
        }
        return data_get($row, $c['k']);
    }

    private function compute(): void
    {
        $rows = collect($this->rows)->values()->all();
        foreach ($this->filters as $f) {
            $v = $this->state['f'][$f['k']] ?? '';
            if ($v !== '') {
                $rows = array_values(array_filter($rows, fn ($r) => (string) ($f['get'])($r) === (string) $v));
            }
        }
        if ($this->state['q'] !== '') {
            $needle = mb_strtolower($this->state['q']);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                $hay = [];
                foreach ($this->cols as $c) {
                    $v = $this->value($r, $c);
                    if (is_scalar($v) || $v === null) {
                        $hay[] = (string) $v;
                    }
                }
                foreach ((array) (is_object($r) ? (method_exists($r, 'toArray') ? $r->toArray() : get_object_vars($r)) : $r) as $v) {
                    if (is_scalar($v)) {
                        $hay[] = (string) $v;
                    }
                }
                return str_contains(mb_strtolower(implode(' ', $hay)), $needle);
            }));
        }
        if ($this->state['sort']) {
            $col = collect($this->cols)->firstWhere('k', $this->state['sort']);
            if ($col) {
                usort($rows, function ($a, $b) use ($col) {
                    $x = $this->value($a, $col);
                    $y = $this->value($b, $col);
                    $x = is_string($x) ? mb_strtolower($x) : ($x ?? '');
                    $y = is_string($y) ? mb_strtolower($y) : ($y ?? '');
                    return $x <=> $y;
                });
                if ($this->state['dir'] === 'desc') {
                    $rows = array_reverse($rows);
                }
            }
        }
        $this->total = count($rows);
        $this->pages = max(1, (int) ceil($this->total / $this->state['per']));
        $this->state['page'] = min($this->state['page'], $this->pages);
        $this->page = array_slice($rows, ($this->state['page'] - 1) * $this->state['per'], $this->state['per']);

        if ($this->export) {                                     // CSV of ALL filtered rows as a data: URI
            $out = [array_map(fn ($c) => strip_tags($c['t'] ?? $c['k']), $this->cols)];
            foreach ($rows as $r) {
                $out[] = array_map(function ($c) use ($r) {
                    $v = $this->value($r, $c);
                    return is_scalar($v) || $v === null ? (string) $v : '';
                }, $this->cols);
            }
            $this->csv = "\xEF\xBB\xBF" . implode("\r\n", array_map(fn ($row) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', html_entity_decode((string) $v)) . '"', $row)), $out));
        }
    }

    public function filterOptions(array $f): array
    {
        $o = $f['opts'] ?? [];
        return is_callable($o) ? $o() : $o;
    }

    public function sortUrl(string $key): string
    {
        $dir = ($this->state['sort'] === $key && $this->state['dir'] === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery([$this->id => array_filter(['q' => $this->state['q'], 'f' => $this->state['f'], 'sort' => $key, 'dir' => $dir, 'per' => $this->state['per']])]);
    }

    public function pageUrl(int $p): string
    {
        return request()->fullUrlWithQuery([$this->id => array_filter(['q' => $this->state['q'], 'f' => $this->state['f'], 'sort' => $this->state['sort'], 'dir' => $this->state['dir'], 'per' => $this->state['per'], 'page' => $p])]);
    }

    public function render()
    {
        return view('components.dtable');
    }
}

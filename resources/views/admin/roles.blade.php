@extends('layouts.app')
@section('title', 'Users & Roles')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Users &amp; Roles
@endsection
@section('heading', 'Users & Roles')
@section('subheading', 'No one who prepares a transaction can approve it alone. Use the role switcher in the avatar menu to test each rule.')

@section('content')
@php
    use App\Domain\Perm;
    use App\Support\Ui;
    $roles = config('roles.roles');
    $can = Perm::can('admin.cfg');
@endphp
@if (session('temp_password'))
    {!! Ui::alert('warn', 'Temporary password for ' . e(session('temp_password.email')), ' <code>' . e(session('temp_password.password')) . '</code> — shown ONCE. Hand it over securely; only its hash is stored.') !!}
    <div style="margin-top:12px"></div>
@endif
<x-tabs :tabs="['users' => 'Users', 'perm' => 'Permission matrix']" :active="$tab" />

@if ($tab === 'users')
    @php
        $cols = [
            ['k' => 'id', 't' => 'ID', 's' => fn ($u) => $u->id, 'r' => fn ($u) => '<b>U' . str_pad($u->id, 3, '0', STR_PAD_LEFT) . '</b>'],
            ['k' => 'name', 't' => 'Name'],
            ['k' => 'role', 't' => 'Role', 's' => fn ($u) => $roles[$u->role]['n'] ?? $u->role],
            ['k' => 'email', 't' => 'Email'],
            ['k' => 'status', 't' => 'Status', 'r' => fn ($u) => Ui::pill($u->status)],
        ];
        $actions = $can ? fn ($u) => '<button class="btn sm" type="button" data-open="user-form" data-fill="' . e(json_encode(['id' => $u->id, 'name' => $u->name, 'role' => $u->role, 'email' => $u->email, 'status' => $u->status])) . '">Edit</button> '
            . '<form method="post" action="' . e(route('ad.user.pw', $u->id)) . '" style="display:inline" data-confirm="' . e('Generate a new temporary password for ' . $u->name . '?') . '">' . csrf_field() . '<button class="btn sm" type="submit">Reset password</button></form>' : null;
        $tools = $can ? '<button class="btn sm pri" type="button" data-open="user-form" data-fill=\'{"id":"","name":"","role":"exec","email":"","status":"Active"}\'>+ Add user</button>' : '';
    @endphp
    <x-dtable id="usr" :cols="$cols" :rows="$users" :actions="$actions" :tools="$tools" />
    @if ($can)
        <x-dialog id="user-form" title="User" :action="route('ad.user.save')" ok="Save user">
            <input type="hidden" name="id" value="{{ old('id') }}">
            <div class="fg">
                <x-field name="name" label="Name" required />
                <x-field name="role" label="Role" type="select" value="exec" :options="array_map(fn ($r) => $r['n'], $roles)" />
                <x-field name="email" label="Email" type="email" required />
                <x-field name="status" label="Status" type="select" value="Active" :options="['Active', 'Inactive']" />
            </div>
            <p class="mut sm">A new user gets a random temporary password, displayed once after saving.</p>
        </x-dialog>
    @endif
@else
    @php $acts = collect(config('roles.perm'))->flatten()->unique()->sort()->values(); @endphp
    <div class="card tbl"><div class="tw"><table>
        <thead><tr><th>Permission</th>@foreach ($roles as $k => $r)<th title="{{ $r['n'] }}">{{ implode(' ', array_slice(explode(' ', $r['n']), 0, 2)) }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($acts as $a)
            <tr><td><code>{{ $a }}</code></td>@foreach ($roles as $k => $r)<td class="r">@if (Perm::can($a, $k))<span style="color:var(--ok);font-weight:700">●</span>@else<span class="mut">·</span>@endif</td>@endforeach</tr>
        @endforeach
        </tbody></table></div></div>
@endif
@endsection

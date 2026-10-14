<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private StaffService $staffService) {}

    public function index(Request $request): View
    {
        $roles = $this->staffService->roles();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role_id' => ['nullable', Rule::in($roles->pluck('id')->all())],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        return view('admin.staff.index', [
            'staff' => $this->staffService->paginate($filters),
            'roles' => $roles,
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.create', ['roles' => $this->staffService->roles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', Rule::in($this->staffService->roles()->pluck('id')->all())],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $staff = $this->staffService->create($data);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.staff.index')->with('success', "Staff account {$staff->name} created.");
    }

    public function edit(User $staff): View
    {
        $this->ensureStaff($staff);

        return view('admin.staff.edit', [
            'staff' => $staff,
            'roles' => $this->staffService->roles(),
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', Rule::in($this->staffService->roles()->pluck('id')->all())],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        try {
            $this->staffService->update($staff, $data);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.staff.index')->with('success', "Staff account {$staff->name} updated.");
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);

        $this->staffService->deactivate($staff);

        return redirect()->route('admin.staff.index')->with('success', "Staff account {$staff->name} deactivated.");
    }

    private function ensureStaff(User $staff): void
    {
        abort_unless($this->staffService->manages($staff), 404);
    }
}

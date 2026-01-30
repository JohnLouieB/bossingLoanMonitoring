<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Member::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $members = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email|max:255',
            'is_active' => 'boolean',
        ]);

        Member::create($validated);

        return redirect()->route('members.index')
            ->with('success', 'Member created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Member $member)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email,' . $member->id . '|max:255',
            'is_active' => 'boolean',
        ]);

        $member->update($validated);

        return redirect()->route('members.index')
            ->with('success', 'Member updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member)
    {
        $member->delete();

        return redirect()->route('members.index')
            ->with('success', 'Member deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\BalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    protected $balanceService;

    public function __construct(BalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    public function index()
    {
        $groups = Auth::user()->groups;
        return view('dashboard', compact('groups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $group = Group::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => Auth::id(),
        ]);

        // Add creator as member
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => Auth::id(),
            'joined_at' => Carbon::now()->toDateString(),
        ]);

        return redirect()->route('groups.show', $group)->with('success', 'Group created successfully!');
    }

    public function show(Group $group)
    {
        // Authorize: check if user is member of group
        if (!$group->members()->where('users.id', Auth::id())->exists()) {
            abort(403, 'You are not a member of this group.');
        }

        $members = GroupMember::where('group_id', $group->id)
            ->with('user')
            ->get();

        $expenses = $group->expenses()
            ->where('status', 'active')
            ->orderBy('expense_date', 'desc')
            ->get();

        $settlements = $group->settlements()
            ->orderBy('settlement_date', 'desc')
            ->get();

        // Use BalanceService to calculate
        $balances = $this->balanceService->getGroupBalances($group);
        $simplifiedDebts = $this->balanceService->getSimplifiedSettlements($group);
        
        $pendingApprovals = $group->expenses()
            ->where('status', 'pending_approval')
            ->orderBy('updated_at', 'desc')
            ->get();

        $selectedLedgerUser = null;
        $selectedLedger = [];
        if (request()->has('view_ledger')) {
            $selectedLedgerUser = User::find(request('view_ledger'));
            if ($selectedLedgerUser && $group->members()->where('users.id', $selectedLedgerUser->id)->exists()) {
                $selectedLedger = $this->balanceService->getUserLedger($group, $selectedLedgerUser);
            }
        }

        return view('groups.show', compact(
            'group',
            'members',
            'expenses',
            'settlements',
            'balances',
            'simplifiedDebts',
            'pendingApprovals',
            'selectedLedgerUser',
            'selectedLedger'
        ));
    }

    public function addMember(Request $request, Group $group)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'joined_at' => 'required|date',
        ]);

        // If user already exists, add them. Else, create guest user.
        $user = User::where('name', 'like', $request->name)
            ->orWhere('email', strtolower($request->name) . '@spreetail.com')
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => strtolower($request->name) . '@spreetail.com',
                'password' => bcrypt('password123'),
            ]);
        }

        // Check if already member
        $isMember = $group->members()->where('users.id', $user->id)->exists();
        if ($isMember) {
            return back()->withErrors(['name' => 'User is already a member of this group.']);
        }

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'joined_at' => $request->joined_at,
        ]);

        return redirect()->route('groups.show', $group)->with('success', "Member '{$user->name}' added successfully.");
    }

    public function removeMember(Request $request, Group $group, User $user)
    {
        $request->validate([
            'left_at' => 'required|date',
        ]);

        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return back()->withErrors(['left_at' => 'Member not found in this group.']);
        }

        $membership->update([
            'left_at' => $request->left_at,
        ]);

        return redirect()->route('groups.show', $group)->with('success', "Member '{$user->name}' left date set to {$request->left_at}.");
    }
}

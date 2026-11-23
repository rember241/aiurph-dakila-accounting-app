<?php

namespace Modules\Accounting\Policies;

use Modules\Accounting\Models\ChartOfAccounts\Account;
use App\Models\User;


/**
 * Class AccountPolicy
 *
 * Policy for accounts
 */
class AccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('accounts.view');
    }


    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Account $a): bool
    {
        return $user->can('accounts.view');
    }


    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('accounts.create');
    }


    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Account $a): bool
    {
        return $user->can('accounts.update');
    }


    /**
     * Determine whether the user can delete the model.
     * Should be admin
     */
    public function delete(User $user, Account $a): bool
    {
        return $user->can('accounts.delete');
    }


    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Account $account): bool
    {
        return false;
    }


}

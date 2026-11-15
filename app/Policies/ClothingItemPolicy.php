<?php

namespace App\Policies;

use App\Models\ClothingItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClothingItemPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClothingItem $clothingItem): Response
    {
        return $user->id === $clothingItem->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClothingItem $clothingItem): Response
    {
        return $this->view($user, $clothingItem);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClothingItem $clothingItem): Response
    {
        return $this->view($user, $clothingItem);
    }
}

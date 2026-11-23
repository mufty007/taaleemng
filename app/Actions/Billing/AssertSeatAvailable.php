<?php

namespace App\Actions\Billing;

use App\Support\Billing\SeatLimits;
use Illuminate\Validation\ValidationException;

/**
 * Hard-stop enrolling a student or hiring staff when the plan’s seat cap is full.
 */
class AssertSeatAvailable
{
    public function __construct(private SeatLimits $seats) {}

    public function forStudent(): void
    {
        if ($this->seats->canAddStudent()) {
            return;
        }

        $snap = $this->seats->studentLimitSnapshot();

        throw ValidationException::withMessages([
            'plan' => "Your {$snap['plan']} plan allows {$snap['max']} students and you already have {$snap['used']}. Upgrade your plan to enrol more.",
        ]);
    }

    public function forStaff(): void
    {
        if ($this->seats->canAddStaff()) {
            return;
        }

        $snap = $this->seats->staffLimitSnapshot();

        throw ValidationException::withMessages([
            'plan' => "Your {$snap['plan']} plan allows {$snap['max']} staff and you already have {$snap['used']}. Upgrade your plan to add more.",
        ]);
    }
}

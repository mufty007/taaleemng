<?php

use App\Actions\Platform\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Models\NumberSequence;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\MissingSchoolContextException;
use App\Support\Tenancy\SchoolAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
 * Every tenant model gets a dataset row here. Each test seeds the model for
 * two schools and proves one school can never see or touch the other's rows.
 */
dataset('tenant models', [
    'school settings' => [fn () => SchoolSetting::query()->create(['key' => 'motto_colour', 'value' => 'green'])],
    'number sequences' => [fn () => NumberSequence::query()->create(['key' => 'invoice_no', 'pattern' => 'INV/{SEQ:4}'])],
]);

beforeEach(function () {
    $this->schoolA = schoolWithRoles(['name' => 'School A']);
    $this->schoolB = schoolWithRoles(['name' => 'School B']);
    $this->current = app(CurrentSchool::class);
});

it('only returns rows that belong to the school in context', function (Closure $make) {
    $rowA = $this->current->run($this->schoolA, $make);
    $rowB = $this->current->run($this->schoolB, $make);

    $this->current->run($this->schoolA, function () use ($rowA, $rowB) {
        $model = $rowA::class;

        expect($model::query()->pluck('id')->all())->toBe([$rowA->id])
            ->and($model::query()->find($rowB->id))->toBeNull();
    });
})->with('tenant models');

it('stamps new rows with the school in context', function (Closure $make) {
    $row = $this->current->run($this->schoolB, $make);

    expect($row->school_id)->toBe($this->schoolB->id);
})->with('tenant models');

it('refuses to query tenant data without a school in context', function (Closure $make) {
    $row = $this->current->run($this->schoolA, $make);

    $this->current->forget();

    $row::query()->get();
})->with('tenant models')->throws(MissingSchoolContextException::class);

it('ignores a mass-assigned school_id', function () {
    $setting = $this->current->run($this->schoolA, fn () => SchoolSetting::query()->create([
        'key' => 'sneaky',
        'value' => 1,
        'school_id' => $this->schoolB->id,
    ]));

    expect($setting->school_id)->toBe($this->schoolA->id);
});

it('refuses to create a row for another school', function () {
    $this->current->run($this->schoolA, fn () => (new SchoolSetting(['key' => 'sneaky', 'value' => 1]))
        ->forceFill(['school_id' => $this->schoolB->id])
        ->save());
})->throws(LogicException::class);

it('refuses to move a row to another school', function () {
    $this->current->run($this->schoolA, function () {
        $setting = SchoolSetting::store('motto_colour', 'green');
        $setting->school_id = $this->schoolB->id;
        $setting->save();
    });
})->throws(LogicException::class);

it('restores the previous school after run()', function () {
    $this->current->set($this->schoolA);

    $inside = $this->current->run($this->schoolB, fn () => $this->current->id());

    expect($inside)->toBe($this->schoolB->id)
        ->and($this->current->id())->toBe($this->schoolA->id);
});

it('runs queued jobs inside the school that dispatched them', function () {
    $job = $this->current->run($this->schoolB, fn () => new RecordsCurrentSchoolJob);

    $this->current->forget();
    dispatch_sync($job);

    expect(RecordsCurrentSchoolJob::$ranFor)->toBe($this->schoolB->id)
        ->and($this->current->check())->toBeFalse();
});

it('returns 404 when a member of one school opens another school', function () {
    $user = memberOf($this->schoolA);

    $this->actingAs($user)->get(route('school.dashboard', $this->schoolB))->assertNotFound();
    $this->actingAs($user)->get(route('school.dashboard', $this->schoolA))->assertOk();
});

it('returns 404 for a school slug that does not exist', function () {
    $user = memberOf($this->schoolA);

    $this->actingAs($user)->get('/app/no-such-school')->assertNotFound();
});

it('blocks a disabled membership', function () {
    $user = memberOf($this->schoolA);
    $user->schools()->updateExistingPivot($this->schoolA->id, ['status' => 'disabled']);

    $this->actingAs($user)->get(route('school.dashboard', $this->schoolA))->assertNotFound();
});

it('shows a suspended school as unavailable to its staff but not to platform admins', function () {
    $school = schoolWithRoles(['status' => 'suspended']);
    $staff = memberOf($school);

    $this->actingAs($staff)->get(route('school.dashboard', $school))
        ->assertForbidden()
        ->assertSee('is not available right now');

    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get(route('school.dashboard', $school))
        ->assertOk();
});

it('scopes roles to the school, so a teacher in one school is not a teacher in another', function () {
    $user = memberOf($this->schoolA, SchoolRole::Teacher);
    app(AddSchoolMember::class)->handle($this->schoolB, $user, SchoolRole::Bursar);

    $rolesIn = fn (School $school) => $this->current->run($school, function () use ($user) {
        $user->unsetRelation('roles');

        return $user->getRoleNames()->all();
    });

    expect($rolesIn($this->schoolA))->toBe(['teacher'])
        ->and($rolesIn($this->schoolB))->toBe(['bursar']);
});

it('redirects guests to the login page', function () {
    $this->get(route('school.dashboard', $this->schoolA))->assertRedirect(route('login'));
});

class RecordsCurrentSchoolJob implements ShouldQueue
{
    use Queueable, SchoolAware;

    public static ?int $ranFor = null;

    public function __construct()
    {
        $this->captureSchool();
    }

    public function handle(CurrentSchool $current): void
    {
        self::$ranFor = $current->id();
    }
}

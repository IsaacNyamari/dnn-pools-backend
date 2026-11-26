<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\VerifyTwoFactorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request, VerifyTwoFactorCode $verifyTwoFactor): JsonResponse
    {
        $user = User::query()->where('email', Str::lower($request->string('email')->value()))->first();
        $password = (string) $request->input('password');

        if ($user === null) {
            // Spend about as long as a password check, so timing does not reveal accounts.
            Hash::make($password);

            throw ApiException::invalidCredentials();
        }

        if (! Hash::check($password, $user->password)) {
            throw ApiException::invalidCredentials();
        }

        return DB::transaction(function () use ($request, $verifyTwoFactor, $user) {
            // Locked so that one recovery code cannot be spent by two requests at once.
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $recoveryCode = null;

            if ($user->hasEnabledTwoFactorAuthentication()) {
                $code = (string) $request->input('two_factor_code');

                if ($code === '') {
                    throw ApiException::twoFactorRequired();
                }

                $recoveryCode = $verifyTwoFactor->handle($user, $code);
            }

            // Throws when there is none, before a recovery code is spent.
            $institutions = $this->institutions($user);

            if ($recoveryCode !== null) {
                $user->replaceRecoveryCode($recoveryCode);
            }

            return new JsonResponse([
                'token' => $user->createToken((string) $request->input('device_name'))->plainTextToken,
                'user' => $this->profile($user),
                'institutions' => $institutions,
            ]);
        });
    }

    public function me(Request $request): JsonResponse
    {
        return new JsonResponse([
            'user' => $this->profile($request->user()),
            'institutions' => $this->institutions($request->user()),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }

    /**
     * The institutions the user can use in the app. Never empty.
     *
     * @return Collection<int, array{id: int, name: string, slug: string, role: string}>
     */
    private function institutions(User $user): Collection
    {
        $institutions = $user->activeInstitutionMemberships()
            ->with('team')
            ->get()
            ->sortBy(fn (Membership $membership) => Str::lower($membership->team->name))
            ->map(fn (Membership $membership) => [
                'id' => $membership->team->id,
                'name' => $membership->team->name,
                'slug' => $membership->team->slug,
                'role' => $membership->role->apiName(),
            ])
            ->values();

        if ($institutions->isEmpty()) {
            throw ApiException::noInstitution();
        }

        return $institutions;
    }
}

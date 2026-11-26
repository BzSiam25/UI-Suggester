<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Http\Resources\GenerationResource;
use App\Http\Resources\UserResource;
use App\Models\Design;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Public profiles plus the owner's own profile editing and avatar. */
class ProfileController extends Controller
{
    public const LINKS = ['github', 'linkedin', 'dribbble', 'behance', 'x'];

    private const PROJECT_COLUMNS = ['id', 'user_id', 'prompt', 'platform', 'screen_type', 'style', 'language', 'framework', 'screens', 'domains', 'provider', 'status', 'meta', 'is_public', 'created_at'];

    public function show(Request $request, string $username): JsonResponse
    {
        $user = $this->findVisible($request, $username);
        $viewer = $request->user('sanctum');
        $isOwner = $viewer && $viewer->id === $user->id;

        $approved = fn () => Design::query()->where('user_id', $user->id)->approved();
        $stats = [
            'designs' => $approved()->count(),
            'likes_received' => (int) $approved()->sum('likes_count'),
            'projects' => $user->generations()->where('is_public', true)->where('status', 'done')->count(),
            'comments' => \App\Models\Comment::where('user_id', $user->id)->count(),
        ];
        if ($isOwner) {
            $stats += [
                'pending_designs' => $user->designs()->where('status', 'pending')->count(),
                'all_projects' => $user->generations()->count(),
                'bookmarks' => $user->bookmarks()->count(),
            ];
        }

        return response()->json([
            'data' => (new UserResource($user))->resolve($request),
            'stats' => $stats,
            'is_owner' => $isOwner,
        ]);
    }

    public function designs(Request $request, string $username)
    {
        $user = $this->findVisible($request, $username);

        return DesignResource::collection(
            $user->designs()->approved()->with(['category', 'user:id,name,username,avatar_path'])->latest('approved_at')->paginate(24)
        );
    }

    public function projects(Request $request, string $username)
    {
        $user = $this->findVisible($request, $username);

        return GenerationResource::collection(
            $user->generations()->where('is_public', true)->where('status', 'done')->with('user:id,name,username,avatar_path')
                ->latest()->paginate(24, self::PROJECT_COLUMNS)
        );
    }

    /** The signed-in user's profile fields. Password changes go through password(). */
    public function update(Request $request): UserResource
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('users', 'username')->ignore($user->id), Rule::notIn(['me', 'admin', 'settings', 'login', 'register'])],
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:280'],
            'location' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url:http,https', 'max:190'],
            'skills' => ['nullable', 'array', 'max:12'],
            'skills.*' => ['nullable', 'string', 'max:30'],
            'links' => ['nullable', 'array'],
            'links.*' => ['nullable', 'url:http,https', 'max:190'],
            'is_profile_public' => ['sometimes', 'boolean'],
        ], [
            'username.regex' => 'Use lowercase letters, numbers and underscores only.',
            'username.not_in' => 'That username is reserved.',
        ]);

        if (array_key_exists('links', $data)) {
            $data['links'] = collect($data['links'] ?? [])->only(self::LINKS)->filter()->all() ?: null;
        }
        if (array_key_exists('skills', $data)) {
            $data['skills'] = collect($data['skills'] ?? [])->map(fn ($s) => trim((string) $s))->filter()
                ->unique(fn ($s) => mb_strtolower($s))->values()->all() ?: null;
        }
        // Accept older clients that still send password fields here.
        if ($request->filled('password')) {
            $this->password($request);
        }

        $user->update(collect($data)->except(['password', 'current_password', 'password_confirmation'])->all());

        return new UserResource($user->fresh());
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8)->symbols(), 'different:current_password'],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed.']);
    }

    /** Square-crops the upload to 320px so every avatar is light and the same shape. */
    public function avatar(Request $request): UserResource
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=64,min_height=64']]);
        $user = $request->user();

        $src = @imagecreatefromstring((string) file_get_contents($request->file('avatar')->getRealPath()));
        abort_unless($src, 422, 'That image could not be read.');

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $size = min(320, $side);
        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        $path = 'avatars/'.$user->id.'-'.Str::lower(Str::random(8)).'.jpg';
        ob_start();
        imagejpeg($dst, null, 86);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($src);
        imagedestroy($dst);

        $this->dropAvatar($user);
        $user->update(['avatar_path' => $path]);

        return new UserResource($user->fresh());
    }

    public function removeAvatar(Request $request): UserResource
    {
        $user = $request->user();
        $this->dropAvatar($user);
        $user->update(['avatar_path' => null]);

        return new UserResource($user->fresh());
    }

    private function dropAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
    }

    /** Private profiles, and banned accounts, are visible only to their owner and admins. */
    private function findVisible(Request $request, string $username): User
    {
        $user = User::where('username', Str::lower($username))->firstOrFail();
        $viewer = $request->user('sanctum');
        $privileged = $viewer && ($viewer->id === $user->id || $viewer->isAdmin());
        abort_unless($privileged || ($user->is_profile_public && ! $user->is_banned), 404);

        return $user;
    }
}

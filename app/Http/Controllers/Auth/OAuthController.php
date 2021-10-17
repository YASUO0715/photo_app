<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\IdentityProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use PhpParser\Node\Stmt\TryCatch;

class OAuthController extends Controller
{
    public function redirectToProvider()
    {
        return Socialite::driver('github')->redirect();
    }

    public function oauthCallback()
    {
        try {
            $socialUser = Socialite::with('github')->user();
        } catch (\Throwable $th) {

            return redirect('/login')->withErrors(['oauth' => '予期せぬエラーが発生しました']);
        }
        // dd($socialUser);

        $user = User::firstOrNew(['email' => $socialUser->getEmail()]);

        //新規ユーザの処理
        if (!$user->exisits) {
            $user->name = $socialUser->getNickname() ?? $socialUser->name;
            $identityProvider = new IdentityProvider([
                'id' => $socialUser->getId(),
                'name' => 'github'
            ]);

            DB::beginTransaction();
            try {
                $user->save();
                $user->identityProvider()->save($identityProvider);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()
                    ->route('login')
                    ->withErrors((['transaction_error' => '保存に失敗しました']));
            }

            Auth::login($user);

            return redirect(RouteServiceProvider::HOME);
        }
    }
}

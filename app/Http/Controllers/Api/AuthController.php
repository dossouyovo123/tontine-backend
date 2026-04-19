<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ── Connexion ──────────────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        $admin->tokens()->delete();
        $token = $admin->createToken('tontine-admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'admin' => [
                'id'        => $admin->id,
                'nom'       => $admin->nom,
                'email'     => $admin->email,
                'telephone' => $admin->telephone ?? null,
            ],
        ]);
    }

    // ── Déconnexion ────────────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    // ── Profil ─────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'                   => 'sometimes|string|max:255',
            'telephone'             => 'sometimes|string',
            'password'              => 'sometimes|string|min:6|confirmed',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $request->user()->update($data);
        return response()->json($request->user());
    }

    // ══════════════════════════════════════════════════════════════════════
    // FLOW MOT DE PASSE OUBLIÉ : 3 étapes
    //   Étape 1 : POST /forgot-password   → envoie OTP 6 chiffres par email
    //   Étape 2 : POST /verify-otp        → vérifie OTP, retourne reset_token
    //   Étape 3 : POST /reset-password    → utilise reset_token pour nouveau mdp
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Étape 1 — Envoyer l'OTP par email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $admin = Admin::where('email', $request->email)->first();

        // Réponse identique que l'email existe ou non (sécurité)
        if (! $admin) {
            return response()->json([
                'message' => 'Si cet email est enregistré, un code OTP a été envoyé.',
            ]);
        }

        // Génère un OTP à 6 chiffres
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Stocke le hash de l'OTP (valide 15 min)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token'      => Hash::make($otp),
                'created_at' => now(),
                'type'       => 'otp',   // colonne optionnelle pour distinguer otp vs reset_token
            ]
        );

        // Envoie l'email
        Mail::to($admin->email)->send(new OtpMail($otp, $admin->nom));

        return response()->json([
            'message' => 'Un code OTP a été envoyé à votre adresse email.',
        ]);
    }

    /**
     * Étape 2 — Vérifier l'OTP et retourner un reset_token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Aucune demande trouvée pour cet email.'], 422);
        }

        // Vérifie expiration (15 min)
        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'OTP expiré. Veuillez en demander un nouveau.'], 422);
        }

        // Vérifie l'OTP
        if (! Hash::check($request->otp, $record->token)) {
            return response()->json(['message' => 'Code OTP incorrect.'], 422);
        }

        // OTP valide → génère un reset_token valable 30 min pour l'étape 3
        $resetToken = Str::random(64);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->update([
                'token'      => Hash::make($resetToken),
                'created_at' => now(),
                'type'       => 'reset_token',
            ]);

        return response()->json([
            'message'     => 'OTP vérifié avec succès.',
            'reset_token' => $resetToken,
            'email'       => $request->email,
        ]);
    }

    /**
     * Étape 3 — Réinitialiser le mot de passe avec le reset_token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email',
            'reset_token'           => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Session expirée. Recommencez depuis le début.'], 422);
        }

        // Vérifie expiration (30 min)
        if (Carbon::parse($record->created_at)->addMinutes(30)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Session expirée. Recommencez depuis le début.'], 422);
        }

        // Vérifie le reset_token
        if (! Hash::check($request->reset_token, $record->token)) {
            return response()->json(['message' => 'Token invalide.'], 422);
        }

        // Met à jour le mot de passe
        Admin::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        // Supprime le token utilisé
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Révoque tous les tokens de session actifs (sécurité)
        $admin = Admin::where('email', $request->email)->first();
        $admin?->tokens()->delete();

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès. Connectez-vous avec votre nouveau mot de passe.',
        ]);
    }
}
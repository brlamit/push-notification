<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Google_Client as GoogleClient;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('home');
    }

    public function saveToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        auth()->user()->update(['fcm_token' => $request->token]);
        return response()->json(['message' => 'Token saved successfully']);
    }

   public function sendNotification(Request $request)
{
    $request->validate([
        'title' => 'required|string',
        'body' => 'required|string',
    ]);

    $firebaseTokens = User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

    if (empty($firebaseTokens)) {
        return redirect()->route('home')->with('error', 'No devices registered for notifications');
    }

    $client = new GoogleClient();
    $client->setAuthConfig(config('services.firebase.credentials_path'));
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    $token = $client->fetchAccessTokenWithAssertion();
    $accessToken = $token['access_token'];

    $projectId = config('services.firebase.project_id');
    $allSuccess = true;
    $responses = [];

    foreach ($firebaseTokens as $token) {
        $response = Http::withHeaders([
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json',
        ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $request->title,
                    'body' => $request->body,
                ],
            ],
        ]);

        if (!$response->successful()) {
            $allSuccess = false;
            $responses[] = $response->json();
        }
    }

    if ($allSuccess) {
        return redirect()->route('home')->with('success', 'Notifications sent successfully');
    } else {
        return redirect()->route('home')->with('error', 'Some notifications failed to send')->with('details', $responses);
    }
}

public function updateToken(Request $request)
{
    $request->validate([
        'token' => 'required|string',
    ]);
    $user = auth()->user();
    $user->fcm_token = $request->token;
    $user->save();
    return response()->json(['message' => 'Token updated successfully']);

}
}
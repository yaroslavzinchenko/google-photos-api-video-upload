<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;
use Google\Auth\OAuth2;

session_start();

class AuthController extends Controller
{
    public $scopes = ['https://www.googleapis.com/auth/photoslibrary'];
    public $redirectURI = 'http://google-photos-api-video-upload.me/redirect';

    public function authorizeApplication()
    {
        $this->connectWithGooglePhotos($this->scopes, $this->redirectURI);
    }

    public function connectWithGooglePhotos(array $scopes, $redirectURI)
    {
        $clientSecretJson = json_decode(file_get_contents('client_secret.json'), true)['web'];
        $clientId = $clientSecretJson['client_id'];
        $clientSecret = $clientSecretJson['client_secret'];

        $oauth2 = new OAuth2([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'authorizationUri' => 'https://accounts.google.com/o/oauth2/v2/auth',
            # Where to return the user to if they accept your request to access their account.
            # You must authorize this URI in the Google API Console.
            'redirectUri' => $redirectURI,
            'tokenCredentialUri' => 'https://www.googleapis.com/oauth2/v4/token',
            'scope' => $scopes,
        ]);

        # The authorization URI will, upon redirecting, return a parameter called code.
        if (!isset($_GET['code'])) {
            $authenticationUrl = $oauth2->buildFullAuthorizationUri(['access_type' => 'offline']);
            header("Location: " . $authenticationUrl);
            exit();
        } else {
            # With the code returned by the OAuth flow, we can retrieve the refresh token.
            $oauth2->setCode($_GET['code']);
            $authToken = $oauth2->fetchAuthToken();
            $refreshToken = $authToken['access_token'];

            # The UserRefreshCredentials will use the refresh token to 'refresh' the credentials when they expire.
            $_SESSION['credentials'] = new UserRefreshCredentials(
                $scopes,
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                ]
            );

            # Return the user to the home page.
            header("Location: /");
            exit();
        }
    }

    public function redirect()
    {
        $this->connectWithGooglePhotos($this->scopes, $this->redirectURI);
    }
}

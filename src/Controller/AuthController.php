<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Validation\Validator;
use App\View;

/** Reģistrācija, pieteikšanās un iziešana. */
final class AuthController
{
    public function __construct(private UserRepository $users)
    {
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            View::redirect('index.php?page=converter');
        }
        View::render('login', ['title' => 'Pieteikšanās', 'error' => null, 'username' => '']);
    }

    public function login(): void
    {
        Csrf::requireValid();
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $error = ($username === '' || $password === '')
            ? 'Ievadiet lietotājvārdu un paroli.'
            : Auth::login($this->users, $username, $password);

        if ($error !== null) {
            http_response_code(401);
            View::render('login', ['title' => 'Pieteikšanās', 'error' => $error, 'username' => $username]);
            return;
        }
        View::flash('success', 'Sveicināti, ' . Auth::user()['username'] . '!');
        View::redirect('index.php?page=converter');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            View::redirect('index.php?page=converter');
        }
        View::render('register', ['title' => 'Reģistrācija', 'v' => new Validator(), 'old' => ['username' => '', 'email' => '']]);
    }

    public function register(): void
    {
        Csrf::requireValid();
        $v = new Validator();

        $username = $v->username('username', $_POST['username'] ?? null);
        $email    = $v->email('email', $_POST['email'] ?? null);
        $password = $v->password('password', $_POST['password'] ?? null, $username);

        if ($password !== null && !hash_equals($password, (string) ($_POST['password2'] ?? ''))) {
            $v->fail('password2', 'Paroles nesakrīt.');
        }
        if ($username !== null && $this->users->usernameExists($username)) {
            $v->fail('username', 'Šāds lietotājvārds jau ir aizņemts.');
        }
        if ($email !== null && $this->users->emailExists($email)) {
            $v->fail('email', 'Šī e-pasta adrese jau ir reģistrēta.');
        }

        if ($v->hasErrors()) {
            http_response_code(422);
            View::render('register', [
                'title' => 'Reģistrācija',
                'v'     => $v,
                'old'   => [
                    'username' => is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '',
                    'email'    => is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '',
                ],
            ]);
            return;
        }

        // Parole tiek glabāta tikai kā jaucējkods (bcrypt/Argon2 ar sāli – PASSWORD_DEFAULT)
        $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
        View::flash('success', 'Konts izveidots. Tagad varat pieteikties.');
        View::redirect('index.php?page=login');
    }

    public function logout(): void
    {
        Csrf::requireValid();
        Auth::logout();
        Auth::startSession();
        View::flash('info', 'Jūs esat izgājis no sistēmas.');
        View::redirect('index.php?page=login');
    }
}

<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace controllers\publics;

    /**
     * Page de connexion.
     */
    class Connect extends \descartes\Controller
    {
        private $internal_user;

        /**
         * Cette fonction est appelée avant toute les autres :.
         *
         * @return void;
         */
        public function __construct()
        {
            $bdd = \descartes\Model::_connect(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD);

            $this->internal_user = new \controllers\internals\User($bdd);

            \controllers\internals\Tool::verifyconnect();
        }

        /**
         * Cette fonction retourne la fenetre de connexion.
         */
        public function login()
        {
            $this->render('connect/login');
        }

        /**
         * Cette fonction connecte un utilisateur, et le redirige sur la page d'accueil.
         *
         * @param string $_POST['mail']     : L'email de l'utilisateur
         * @param string $_POST['password'] : Le mot de passe de l'utilisateur
         */
        public function connection()
        {
            $email = $_POST['mail'] ?? false;
            $password = $_POST['password'] ?? false;

            $user = $this->internal_user->check_credentials($email, $password);
            if (!$user)
            {
                \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Email ou mot de passe invalide.');

                return $this->redirect(\descartes\Router::url('Connect', 'login'));
            }

            $_SESSION['connect'] = true;
            $_SESSION['user'] = $user;

            return $this->redirect(\descartes\Router::url('Dashboard', 'show'));
        }

        /**
         * Cette fonction retourne la fenetre de changement de password.
         *
         * @return void;
         */
        public function forget_password()
        {
            $this->render('connect/forget-password');
        }

        /**
         * Cette fonction envoi un email contenant un lien pour re-générer un password oublié.
         *
         * @param string $csrf           : jeton csrf
         * @param string $_POST['email'] : L'email pour lequel on veut envoyer un nouveau password
         */
        public function send_reset_password($csrf)
        {
            if (!$this->verify_csrf($csrf))
            {
                \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Jeton CSRF invalid !');

                return $this->redirect(\descartes\Router::url('Connect', 'forget_password'));
            }

            $email = $_POST['email'] ?? false;
            $user = $this->internal_user->get_by_email($email);

            if (!$email || !$user)
            {
                \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Aucun utilisateur n\'existe pour cette adresse mail.');

                return $this->redirect(\descartes\Router::url('Connect', 'forget_password'));
            }

            $Tokenista = new \Ingenerator\Tokenista(APP_SECRET);
            $token = $Tokenista->generate(3600, ['user_id' => $user['id']]);

            $reset_link = \descartes\Router::url('Connect', 'reset_password', ['user_id' => $user['id'], 'token' => $token]);

            \controllers\internals\Tool::send_email($email, EMAIL_RESET_PASSWORD, ['reset_link' => $reset_link]);

            return $this->render('connect/send-reset-password');
        }

        /**
         * Cette fonction permet à un utilisateur de re-définir son mot de passe.
         *
         * @param int       $user_id           : L'id du user dont on veut modifier le password
         * @param string    $token             : Le token permetttant de vérifier que l'opération est légitime
         * @param optionnal $_POST['password'] : Le nouveau password à utiliser
         */
        public function reset_password($user_id, $token)
        {
            $password = $_POST['password'] ?? false;

            $Tokenista = new \Ingenerator\Tokenista(APP_SECRET);

            if (!$Tokenista->isValid($token, ['user_id' => $user_id]))
            {
                return $this->render('connect/reset-password-invalid');
            }

            if (!$password)
            {
                return $this->render('connect/reset-password');
            }

            $this->internal_user->update_password($user_id, $password);

            return $this->render('connect/reset-password-done');
        }

        /**
         * Cette fonction déconnecte un utilisateur et le renvoie sur la page d'accueil.
         */
        public function logout()
        {
            session_unset();
            session_destroy();

            return $this->redirect(\descartes\Router::url('Connect', 'login'));
        }
    }
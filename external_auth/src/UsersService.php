<?php

declare(strict_types=1);

namespace Drupal\external_auth;

use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @todo Add class description.
 */
final class UsersService {

  public function getExternalIdByName(string $name): ?int {
    $users = json_decode(file_get_contents(__DIR__ . '/../fake-users.json'));

    $user = array_filter($users, function ($user) use ($name) {
      return $user->mail === $name;
    });

    if (empty($user)) {
      return NULL;
    }

    return array_shift($user)->crm_id;
  }

  public function getExternalUserById(string $id) {
    $users = json_decode(file_get_contents(__DIR__ . '/../fake-users.json'));

    $user = array_filter($users, function ($user) use ($id) {
      return strval($user->crm_id) === strval($id);
    });

    if (empty($user)) {
      return NULL;
    }

    return array_shift($user);
  }

  public function getDrupalUserByExternalId(int $externalId) {
    $user_query = \Drupal::entityQuery('user')
      ->condition('field_crm_id', $externalId)
      ->range(0, 1)
      ->accessCheck(FALSE);

    $uids = $user_query->execute();

    if (!empty($uids)) {
      $uid = reset($uids);
      $user = User::load($uid);

      return $user;
    }

    return NULL;
  }

  public function loginOrRegisterUser($externalId, $password) {
    $drupal_user = $this->getDrupalUserByExternalId($externalId);
    $externalUser = $this->getExternalUserById(strval($externalId));

    if ($drupal_user) {
      if (\Drupal::service('password')->check($password, $drupal_user->getPassword())) {
        $drupal_user->setEmail($externalUser->mail);
        $drupal_user->save();

        user_login_finalize($drupal_user);
        return $drupal_user;
      }
    } else {
      $user = User::create();
      $user->setUsername('crm_user_' . $externalId);
      $user->set('field_crm_id', $externalId);
      $user->setEmail($externalUser->mail);
      $user->activate();
      $user->save();

      $password_reset_link = Url::fromRoute('user.pass', [], ['query' => ['user' => $user->id()]]);
      $password_reset_link = $password_reset_link->toString();

      $response = new RedirectResponse($password_reset_link);
      $response->send();
    }
  }
}

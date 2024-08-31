<?php

namespace App\Filament\Admin\Resources\EmailResource;

class DovecotConfigGenerator
{
    public function generate(string $email, string $password): string
    {
        $domain = explode('@', $email)[1];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        return <<<EOT
mail_location = maildir:/var/mail/$domain/$email
namespace inbox {
  inbox = yes
}
protocol lda {
  postmaster_address = postmaster@$domain
}
auth_mechanisms = plain login
passdb {
  driver = passwd-file
  args = scheme=CRYPT username_format=%u /etc/dovecot/users
}
userdb {
  driver = static
  args = uid=vmail gid=vmail home=/var/mail/%d/%n
}
service auth {
  unix_listener auth-userdb {
    mode = 0600
    user = vmail
  }
}
$email:{CRYPT}$hashedPassword
EOT;
    }
}
<?php

namespace App\Filament\Admin\Resources\EmailResource;

class DovecotConfigGenerator
{
    public function generate(string $email, string $password, array $forwardingRules): string
    {
        $domain = explode('@', $email)[1];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $forwardingConfig = '';
        if (!empty($forwardingRules)) {
            $forwardingConfig = "sieve_before = /var/mail/$domain/$email/.dovecot.sieve\n";
        }

        $config = <<<EOT
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
$forwardingConfig
$email:{CRYPT}$hashedPassword
EOT;

        // Generate Sieve script for forwarding
        if (!empty($forwardingRules)) {
            $sieveScript = $this->generateSieveScript($forwardingRules);
            $sieveFilePath = "/var/mail/$domain/$email/.dovecot.sieve";
            file_put_contents($sieveFilePath, $sieveScript);
        }

        return $config;
    }

    private function generateSieveScript(array $forwardingRules): string
    {
        $script = "require [\"copy\", \"fileinto\"];\n\n";

        foreach ($forwardingRules as $rule) {
            $destination = $rule['destination'];
            $script .= "if true {\n";
            $script .= "    redirect :copy \"$destination\";\n";
            $script .= "}\n\n";
        }

        return $script;
    }
}
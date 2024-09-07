<?php

namespace App\Services;

use phpseclib3\Net\SFTP;
use App\Models\Domain;

class SftpService
{
    protected SFTP $sftp;

    public function connect(Domain $domain)
    {
        $this->sftp = new SFTP($domain->domain_name, 2222);
        if (!$this->sftp->login($domain->sftp_username, $domain->sftp_password)) {
            throw new \Exception('Login failed');
        }
    }

    public function listFiles($directory = '.')
    {
        return $this->sftp->nlist($directory);
    }

    public function uploadFile($localFile, $remoteFile)
    {
        return $this->sftp->put($remoteFile, $localFile, SFTP::SOURCE_LOCAL_FILE);
    }

    public function downloadFile($remoteFile, $localFile)
    {
        return $this->sftp->get($remoteFile, $localFile);
    }

    public function deleteFile($remoteFile)
    {
        return $this->sftp->delete($remoteFile);
    }

    public function createDirectory($directory)
    {
        return $this->sftp->mkdir($directory);
    }

    public function removeDirectory($directory)
    {
        return $this->sftp->rmdir($directory);
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DockerComposeService
{
    public function generateComposeFile(array $data, $hostingPlan): void
    {
        $composeContent = $this->generateContent($data, $hostingPlan);
        
        Storage::disk('local')->put(
            'docker-compose-'.$data['domain_name'].'.yml',
            $composeContent
        );
    }

    public function startServices(string $domainName): void
    {
        $composeFile = storage_path('app/docker-compose-'.$domainName.'.yml');

        $process = new Process(['docker-compose', '-f', $composeFile, 'up', '-d']);
        $process->run();
    }

    protected function generateContent(array $data, $hostingPlan): string
    {
        return <<<EOT
version: '3.8'

services:
  web:
    image: nginx:latest
    container_name: {$data['domain_name']}
    environment:
      - VIRTUAL_HOST={$data['virtual_host']}
      - LETSENCRYPT_HOST={$data['letsencrypt_host']}
      - LETSENCRYPT_EMAIL={$data['letsencrypt_email']}
    volumes:
      - ./html:/usr/share/nginx/html
      - ./vhost.d:/etc/nginx/conf.d
    networks:
      - nginx-proxy
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: {$hostingPlan->disk_space}M
        reservations:
          cpus: '0.25'
          memory: {$hostingPlan->bandwidth}M

  sftp:
    image: atmoz/sftp
    container_name: {$data['domain_name']}_sftp
    volumes:
      - ./html:/home/{$data['domain_name']}/html
    ports:
      - "2222:22"
    command: {$data['domain_name']}:{$data['sftp_password']}:1001

  ssh:
    image: linuxserver/openssh-server
    container_name: {$data['domain_name']}_ssh
    environment:
      - PUID=1000
      - PGID=1000
      - TZ=Europe/London
      - PASSWORD_ACCESS=true
      - USER_NAME={$data['domain_name']}
      - USER_PASSWORD={$data['ssh_password']}
    volumes:
      - ./html:/home/{$data['domain_name']}/html
    ports:
      - "2223:2222"

networks:
  nginx-proxy:
    external:
      name: nginx-proxy
EOT;
    }

    public function generateSftpPassword(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function generateSshPassword(): string
    {
        return bin2hex(random_bytes(8));
    }
}
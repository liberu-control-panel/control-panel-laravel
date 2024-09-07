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
      - proxy-network
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: {$hostingPlan->disk_space}M
        reservations:
          cpus: '0.25'
          memory: {$hostingPlan->bandwidth}M

  control-panel:
    build:
      context: .
      dockerfile: Dockerfile
    restart: always
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_HOST=mysql
      - DB_DATABASE=myapp
      - DB_USERNAME=myapp
      - DB_PASSWORD_FILE=/run/secrets/db_password
      - VIRTUAL_HOST=${CONTROL_PANEL_DOMAIN}
      - LETSENCRYPT_HOST=${CONTROL_PANEL_DOMAIN}
      - LETSENCRYPT_EMAIL=${LETSENCRYPT_EMAIL}
    volumes:
      - ./:/var/www/html
    depends_on:
      - mysql
    secrets:
      - db_password
    networks:
      - proxy-network

networks:
  proxy-network:
    external:
      name: proxy-network
EOT;
    }
}
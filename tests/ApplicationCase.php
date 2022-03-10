<?php

namespace DansMaCulotte\Flysystem\Cloudinary\Test;

include_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ .'/Helpers.php';

use DansMaCulotte\Flysystem\Cloudinary\CloudinaryAdapter as Adapter;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

class ApplicationCase extends TestCase
{
    const IMAGE = __DIR__ . '/logo-git.png';
    const IMAGE_UPDATE = __DIR__ . '/logo-update.png';

    const ROOT = 'cloudinary_test';

    protected static $image_id;

    protected static $config;

    /**
     * @var Filesystem
     */
    private $adapter;

    public function imageName(): string
    {
        return  md5(strtotime('now'));
    }

    public function adapter(): Filesystem
    {
        if (!$this->adapter instanceof Filesystem) {
            $this->adapter = $this->createFilesystemAdapter();
        }
        return $this->adapter;
    }

    public function getContentFile()
    {
        return file_get_contents(self::IMAGE);
    }

    protected function createFilesystemAdapter()
    {
        return   new Filesystem($this->createCloudinaryInstance());
    }

    protected function createCloudinaryInstance()
    {
        self::$config = [
            'cloud' => [
                'api_key' => $_ENV['CLOUDINARY_API_KEY'],
                'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
                'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
            ]
        ];

        return new Adapter(self::$config);
    }
    

    protected function makePathFile($file): string
    {
        return sprintf('%s/%s', self::ROOT, $file);
    }
}

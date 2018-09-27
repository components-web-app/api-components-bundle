<?php

namespace Silverback\ApiComponentBundle\Uploader;

use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FixtureFileUploader
{
    private $fileUploader;
    private $uploadsDir;

    public function __construct(
        FileUploader $fileUploader,
        string $projectDir = ''
    )
    {
        $this->fileUploader = $fileUploader;
        $this->uploadsDir = sprintf('%s/var/uploads/', $projectDir);
    }

    /**
     * @param AbstractFactory $factory
     * @param array $data
     * @param File $file
     * @param string $field
     * @return FileInterface
     * @throws \Exception
     */
    public function upload(AbstractFactory $factory, array $data, File $file, string $field = 'filePath'): FileInterface
    {
        $entity = $factory->create($data);
        if (!($entity instanceof FileInterface)) {
            throw new \Exception('Invalid entity returned from FixtureFileUploader::upload factory');
        }
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        fclose($tempFile);

        $fs = new Filesystem();
        $fs->copy($file->getRealPath(), $tempPath, true);
        $uploadedFile = new UploadedFile(
            $tempPath,
            $file->getFilename(),
            $file->getMimeType(),
            null,
            true
        );
        $this->fileUploader->upload($entity, $field, $uploadedFile);
        return $entity;
    }
}

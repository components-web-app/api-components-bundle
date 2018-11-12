<?php

namespace Silverback\ApiComponentBundle\File\Uploader;

use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FixtureFileUploader
{
    private $fileUploader;

    public function __construct(
        FileUploader $fileUploader
    ) {
        $this->fileUploader = $fileUploader;
    }

    /**
     * @param FileInterface $entity
     * @param File $file
     * @param string $field
     * @return \Silverback\ApiComponentBundle\Entity\Component\FileInterface
     * @throws \Exception
     */
    public function upload(FileInterface $entity, File $file, string $field = 'filePath'): FileInterface
    {
        if (!($entity instanceof FileInterface)) {
            throw new \Exception('Invalid entity returned from FixtureFileUploader::upload factory');
        }
        $tempFile = tmpfile();
        if (false === $tempFile) {
            throw new \Exception('Could not create temporary file');
        }
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

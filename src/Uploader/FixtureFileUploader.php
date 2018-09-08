<?php

namespace Silverback\ApiComponentBundle\Uploader;

use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;
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
     * @param AbstractComponentFactory $factory
     * @param array $data
     * @param string $file
     * @param string $field
     * @return FileInterface
     * @throws \Exception
     */
    public function upload(AbstractComponentFactory $factory, array $data, File $file, string $field = 'filePath'): FileInterface
    {
        $entity = $factory->create($data);
        if (!($entity instanceof FileInterface)) {
            throw new \Exception('Invalid entity returned from FixtureFileUploader::upload factory');
        }
        $uploadedFile = new UploadedFile(
            $file->getRealPath(),
            $file->getFilename(),
            null,
            null,
            true
        );
        $this->fileUploader->upload($entity, $field, $uploadedFile);
        return $entity;
    }
}

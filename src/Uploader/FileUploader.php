<?php

namespace Silverback\ApiComponentBundle\Uploader;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\PropertyAccess;

class FileUploader
{
    private $rootPath;
    private $validator;
    private $propertyAccessor;
    private $em;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        array $rootPaths = []
    ) {
        $this->validator = $validator;
        $this->rootPath = $rootPaths['uploads'];
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->em = $em;
    }

    private function getRealPath(string $moveToDir, string $filename): string
    {
        return rtrim($moveToDir, '/') . '/' . $filename;
    }

    private function getNewFilename(string $moveToDir, UploadedFile $file): string
    {
        $fs = new Filesystem();

        $ext = $file->guessExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = $basename.'.'.$ext;
        $i=0;
        while ($fs->exists($this->getRealPath($moveToDir, $filename))) {
            $i++;
            $filename = $basename.".$i.$ext";
        }
        return $filename;
    }

    private function validateNewFile($entity, $field, UploadedFile $file): void
    {
        $this->propertyAccessor->setValue($entity, $field, $file);
        $errors = $this->validator->validate($entity);
        if ($errors !== null && \count($errors)) {
            throw new InvalidArgumentException((string) $errors);
        }
    }

    private function unlinkFile(File $currentFile): void
    {
        $oldFilePath = $currentFile->getRealPath();
        if (!is_writable($oldFilePath)) {
            throw new RuntimeException('The existing file cannot be deleted. File upload aborted');
        }
        unlink($currentFile->getRealPath());
    }

    public function upload(FileInterface $entity, string $field, UploadedFile $file): FileInterface
    {
        /** @var File|null|string $currentFile */
        $currentFile = $this->propertyAccessor->getValue($entity, $field);

        // Set to the new file and validate it before we upload and persist any changes
        $this->validateNewFile($entity, $field, $file);

        // Validation passed, remove old file first (in case we don't have permission to do it)
        if ($currentFile) {
            try{
                $this->unlinkFile(new File($currentFile));
            }catch(FileNotFoundException $e){}
        }

        // Old file removed, let's update!
        $moveToDir = sprintf('%s/%s', $this->rootPath, $entity->getDir());
        $filename = $this->getNewFilename($moveToDir, $file);
        $movedFile = $file->move($moveToDir, $filename);
        $this->propertyAccessor->setValue($entity, $field, $movedFile->getRealPath());
        $this->em->flush();
        return $entity;
    }
}

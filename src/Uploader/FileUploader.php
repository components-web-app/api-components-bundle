<?php

namespace Silverback\ApiComponentBundle\Uploader;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
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
    )
    {
        $this->validator = $validator;
        $this->rootPath = $rootPaths[0];
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->em = $em;
    }

    private function getRealPath ($filename): string
    {
        return rtrim($this->rootPath, '/') . '/' . $filename;
    }

    private function getNewFilename(UploadedFile $file): string
    {
        $fs = new Filesystem();

        $ext = $file->guessExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = $basename.'.'.$ext;
        $i=0;
        while($fs->exists($this->getRealPath($filename))) {
            $i++;
            $filename = $basename.".$i.$ext";
        }
        return $filename;
    }

    private function validateNewFile($entity, $field, UploadedFile $file): void
    {
        $this->propertyAccessor->setValue($entity, $field, $file);
        $errors = $this->validator->validate($entity);
        if (\count($errors)) {
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

    public function upload($entity, string $field, UploadedFile $file)
    {
        /** @var File|null|string $currentFile */
        $currentFile = $this->propertyAccessor->getValue($entity, $field);

        // Set to the new file and validate it before we upload and persist any changes
        $this->validateNewFile($entity, $field, $file);

        // Validation passed, remove old file first (in case we don't have permission to do it)
        if ($currentFile instanceof File) {
            $this->unlinkFile($currentFile);
        }

        // Old file removed, let's update!
        $filename = $this->getNewFilename($file);
        $file->move($this->rootPath, $filename);
        // We may need this, but perhaps not so we will try to persist the UploadedFile entity first as it extends File anyway
        // $movedFile = new File($this->getRealPath($filename));
        $this->propertyAccessor->setValue($entity, $field, $file);
        $this->em->flush();
        return $entity;
    }
}

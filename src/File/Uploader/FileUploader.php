<?php

namespace Silverback\ApiComponentBundle\File\Uploader;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface as ApiValidator;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileUploader
{
    private $em;
    private $resourceMetadataFactory;
    private $validator;
    private $apiValidator;
    private $rootPath;
    private $propertyAccessor;

    public function __construct(
        EntityManagerInterface $em,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        ValidatorInterface $validator,
        ApiValidator $apiValidator,
        array $rootPaths = []
    ) {
        $this->em = $em;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->validator = $validator;
        $this->apiValidator = $apiValidator;
        $this->rootPath = $rootPaths['uploads'];
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
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
        $filename = "$basename.$ext";
        $i = 0;
        while ($fs->exists($this->getRealPath($moveToDir, $filename))) {
            $i++;
            $filename = "$basename.$i.$ext";
        }
        return $filename;
    }

    private function validateNewFile($entity, $field, UploadedFile $file, array $validationGroups): void
    {
        $errors = $this->validator->validatePropertyValue($entity, $field, $file->getRealPath(), $validationGroups);
        if ($errors !== null && \count($errors)) {
            throw new ValidationException($errors);
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

    public function upload(FileInterface $entity, string $field, UploadedFile $file, string $itemOperationName = 'post'): FileInterface
    {
        if ($file->getFilename() === '') {
            $template = 'The file was not uploaded. It is likely that the file size was larger than %s';
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(sprintf($template, ini_get('upload_max_filesize')), $template, [ ini_get('upload_max_filesize') ], $file, 'filename', $file->getFilename())
            ]));
        }
        $resourceMetadata = $this->resourceMetadataFactory->create(get_class($entity));
        $validationGroups = $resourceMetadata->getOperationAttribute(
            ['item_operation_name' => $itemOperationName],
            'validation_groups',
            [],
            true
        );

        /** @var File|null|string $currentFile */
        $currentFile = $this->propertyAccessor->getValue($entity, $field);

        // Set to the new file and validate it before we upload and persist any changes
        $this->validateNewFile($entity, $field, $file, $validationGroups);

        // Validation passed, remove old file first (in case we don't have permission to do it)
        if ($currentFile) {
            try {
                $this->unlinkFile(new File($currentFile));
            } catch (FileNotFoundException $e) {
                // If the file did not exist, there's no problem if it was not found as we are trying to delete it anyway
            }
        }
        // Old file removed, let's update!
        $moveToDir = sprintf('%s/%s', $this->rootPath, $entity->getDir());
        $filename = $this->getNewFilename($moveToDir, $file);
        $movedFile = $file->move($moveToDir, $filename);
        $this->propertyAccessor->setValue($entity, $field, $movedFile->getRealPath());

        $this->apiValidator->validate($entity, ['groups' => $validationGroups]);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }
}

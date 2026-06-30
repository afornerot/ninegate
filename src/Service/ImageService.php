<?php

namespace App\Service;

class ImageService
{
    // Hauteur d'une image
    public function getHeight(string $image): int
    {
        $size = getimagesize($image);
        $height = $size[1];

        return $height;
    }

    // Largeur d'une imgage
    public function getWidth(string $image): int
    {
        $size = getimagesize($image);
        $width = $size[0];

        return $width;
    }

    public function resizeImage(string $image, int $maxWidth, int $maxHeight): void
    {
        list($width, $height, $imageType) = getimagesize($image);
        $imageType = image_type_to_mime_type($imageType);

        // Définir le pourcentage de réduction de l'image
        $scale = $maxHeight / $height;
        if (($width * $scale) > $maxWidth) {
            $scale = $maxWidth / $width;
        }

        // Création de l'image redimentionnée
        $newImageWidth = (int) ceil($width * $scale);
        $newImageHeight = (int) ceil($height * $scale);
        $newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);

        switch ($imageType) {
            case 'image/gif':
                $source = imagecreatefromgif($image);
                break;
            case 'image/pjpeg':
            case 'image/jpeg':
            case 'image/jpg':
                $source = imagecreatefromjpeg($image);
                break;
            case 'image/png':
            case 'image/x-png':
                $source = imagecreatefrompng($image);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($image);
                break;
            default:
                $source= imagecreatefromgif($image);
                break;
        }

        imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newImageWidth, $newImageHeight, $width, $height);

        switch ($imageType) {
            case 'image/gif':
                imagegif($newImage, $image);
                break;
            case 'image/pjpeg':
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($newImage, $image, 90);
                break;
            case 'image/png':
            case 'image/x-png':
                imagepng($newImage, $image);
                break;
            case 'image/webp':
                imagewebp($newImage, $image, 90);
        }
    }

    public function cropImage(string $image, string $thumb, int $x, int $y, int $cropWidth, int $cropHeight, int $maxWidth, int $maxHeight): void
    {
        list($width, $height, $imageType) = getimagesize($image);
        $imageType = image_type_to_mime_type($imageType);

        // Définir le pourcentage de réduction de l'image
        $scale = $maxHeight / $cropHeight;
        if (($cropWidth * $scale) > $maxWidth) {
            $scale = $maxWidth / $cropWidth;
        }

        // Création de l'image redimentionnée
        $newImageWidth = (int) ceil($cropWidth * $scale);
        $newImageHeight = (int) ceil($cropHeight * $scale);

        $newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);

        switch ($imageType) {
            case 'image/gif':
                $source = imagecreatefromgif($image);
                break;
            case 'image/pjpeg':
            case 'image/jpeg':
            case 'image/jpg':
                $source = imagecreatefromjpeg($image);
                break;
            case 'image/png':
            case 'image/x-png':
                $source = imagecreatefrompng($image);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($image);
                break;
            default:
                $source= imagecreatefromgif($image);
                break;
        }

        imagecopyresampled($newImage, $source, 0, 0, $x, $y, $newImageWidth, $newImageHeight, $cropWidth, $cropHeight);

        switch ($imageType) {
            case 'image/gif':
                imagegif($newImage, $thumb);
                break;
            case 'image/pjpeg':
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($newImage, $thumb, 90);
                break;
            case 'image/png':
            case 'image/x-png':
                imagepng($newImage, $thumb);
                break;
            case 'image/webp':
                imagewebp($newImage, $thumb, 90);
        }
    }
}

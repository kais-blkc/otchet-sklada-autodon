<?php

/**
 * Сжимает изображение
 *
 * @param string $source Путь к исходному изображению
 * @param string $destination Путь для сохранения сжатого изображения
 * @param int $quality Качество (0-100 для JPEG/WebP, 0-9 для PNG)
 * @param bool $convertToWebP Конвертировать ли изображение в WebP
 * @return bool true при успехе, false при ошибке
 */
function compressImage(string $source, string $destination, int $quality = 70, bool $convertToWebP = true)
{
  if (!file_exists($source)) return false;

  $info = getimagesize($source);
  if ($info === false) return false;

  $mime = $info['mime'];
  $image = null;

  switch ($mime) {
    case 'image/jpeg':
      $image = imagecreatefromjpeg($source);
      break;
    case 'image/png':
      $image = imagecreatefrompng($source);
      break;
    case 'image/webp':
      $image = imagecreatefromwebp($source);
      break;
    default:
      return false;
  }
  if (!$image) return false;

  if ($convertToWebP) {
    $result = imagewebp($image, $destination, $quality);
  } else {
    switch ($mime) {
      case 'image/jpeg':
        $result = imagejpeg($image, $destination, $quality);
        break;
      case 'image/png':
        $pngCompression = intval((100 - $quality) / 10); // преобразуем 0-100 -> 0-9
        $result = imagepng($image, $destination, $pngCompression);
        break;
      case 'image/webp':
        $result = imagewebp($image, $destination, $quality);
        break;
    }
  }

  imagedestroy($image);
  return $result;
}

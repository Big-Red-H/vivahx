<?php
declare(strict_types=1);

/**
 * Screenshot uploads. Each image is checked, re-saved (which strips hidden metadata such as GPS
 * location), shrunk if very wide, and kept on the server until the edit is sent. WebP, which old
 * browsers can't show, becomes PNG.
 */
final class Images
{
    /**
     * @param array $upload one entry from $_FILES
     * @param array $taken file names already in use in this project's screenshots
     * @return array ['id', 'name', 'file', 'mime']
     */
    public static function accept(array $upload, string $owner, array $taken): array
    {
        $limits = config()['limits'] ?? [];
        $maxBytes = (int) ($limits['image_max_bytes'] ?? 5_000_000);
        $maxWidth = (int) ($limits['image_max_width'] ?? 1600);

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The upload did not finish. Please try again.');
        }
        if ($upload['size'] > $maxBytes) {
            throw new InvalidArgumentException(sprintf('%s is too large (limit %d MB).', $upload['name'], $maxBytes / 1_000_000));
        }
        $info = @getimagesize($upload['tmp_name']);
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'png'];
        if (!$info || !isset($types[$info[2]])) {
            throw new InvalidArgumentException(sprintf('%s is not a PNG, JPG, GIF or WebP image.', $upload['name']));
        }
        if ($info[2] === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) {
            throw new InvalidArgumentException(sprintf('%s is a WebP image, which this server cannot convert. Please upload a PNG or JPG.', $upload['name']));
        }
        $ext = $types[$info[2]];

        $dir = data_dir('uploads/' . preg_replace('/[^a-z0-9-]/', '', $owner));
        self::removeOldUploads($dir);
        $id = bin2hex(random_bytes(8));
        $file = "$dir/$id.$ext";

        if ($info[2] === IMAGETYPE_GIF) {
            // Re-saving would break animation, and GIFs carry no location data.
            move_uploaded_file($upload['tmp_name'], $file) || copy($upload['tmp_name'], $file);
        } else {
            $image = match ($info[2]) {
                IMAGETYPE_PNG => imagecreatefrompng($upload['tmp_name']),
                IMAGETYPE_JPEG => imagecreatefromjpeg($upload['tmp_name']),
                IMAGETYPE_WEBP => imagecreatefromwebp($upload['tmp_name']),
            };
            if (!$image) {
                throw new InvalidArgumentException(sprintf('%s could not be read.', $upload['name']));
            }
            if (imagesx($image) > $maxWidth) {
                $image = imagescale($image, $maxWidth) ?: $image;
            }
            imagesavealpha($image, true);
            $ext === 'jpg' ? imagejpeg($image, $file, 88) : imagepng($image, $file, 9);
        }

        return [
            'id' => $id,
            'name' => self::freeName(self::cleanName($upload['name'], $ext), $taken),
            'file' => $file,
            'mime' => $ext === 'jpg' ? 'image/jpeg' : "image/$ext",
        ];
    }

    private static function cleanName(string $original, string $ext): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $base), '._-');
        return substr($base !== '' ? $base : 'screenshot', 0, 80) . ".$ext";
    }

    private static function freeName(string $name, array $taken): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        for ($n = 2, $candidate = $name; in_array(strtolower($candidate), array_map('strtolower', $taken), true); $n++) {
            $candidate = "$base-$n.$ext";
        }
        return $candidate;
    }

    private static function removeOldUploads(string $dir): void
    {
        foreach (glob("$dir/*") ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                unlink($old);
            }
        }
    }
}

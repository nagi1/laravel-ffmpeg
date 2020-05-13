<?php

namespace Pbmedia\LaravelFFMpeg;

use FFMpeg\Format\FormatInterface;
use Illuminate\Support\Collection;
use Pbmedia\LaravelFFMpeg\Drivers\PHPFFMpeg;
use Pbmedia\LaravelFFMpeg\FFMpeg\AdvancedOutputMapping;
use Pbmedia\LaravelFFMpeg\Filesystem\Disk;
use Pbmedia\LaravelFFMpeg\Filesystem\Media;

class MediaExporter
{
    protected PHPFFMpeg $driver;
    private ?FormatInterface $format = null;
    protected Collection $maps;
    protected ?string $visibility = null;
    private ?Disk $toDisk         = null;

    public function __construct(PHPFFMpeg $driver)
    {
        $this->driver = $driver;

        $this->maps = new Collection;
    }

    protected function getDisk(): Disk
    {
        if ($this->toDisk) {
            return $this->toDisk;
        }

        $media = $this->driver->getMediaCollection();

        return $this->toDisk = $media->first()->getDisk();
    }

    public function inFormat(FormatInterface $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function addFormatOutputMapping(FormatInterface $format, Media $output, array $outs, $forceDisableAudio = false, $forceDisableVideo = false)
    {
        $this->maps->push(
            new AdvancedOutputMapping($outs, $format, $output, $forceDisableAudio, $forceDisableVideo)
        );

        return $this;
    }

    public function toDisk($disk)
    {
        $this->toDisk = Disk::make($disk);

        return $this;
    }

    public function withVisibility(string $visibility)
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getCommand(string $path = null): string
    {
        $this->driver->getBasicFilters()->each->apply($this->driver, $this->maps);

        $this->maps->each->apply($this->driver->get());

        return $this->driver->getFinalCommand(
            $this->format,
            $path ? $this->getDisk()->makeMedia($path)->getLocalPath() : null
        );
    }

    public function save(string $path = null): MediaOpener
    {
        if ($this->maps->isNotEmpty()) {
            return $this->saveWithMappings();
        }

        $outputMedia = $this->getDisk()->makeMedia($path);

        $this->driver->save($this->format, $outputMedia->getLocalPath());

        $outputMedia->copyAllFromTemporaryDirectory($this->visibility);
        $outputMedia->setVisibility($this->visibility);

        return $this->getMediaOpener();
    }

    private function saveWithMappings(): MediaOpener
    {
        $this->driver->getBasicFilters()->each->apply($this->driver, $this->maps);

        $this->maps->each->apply($this->driver->get());

        $this->driver->save();

        $this->maps->map->getOutputMedia()->each->copyAllFromTemporaryDirectory($this->visibility);

        return $this->getMediaOpener();
    }

    private function getMediaOpener(): MediaOpener
    {
        return new MediaOpener(
            $this->driver->getMediaCollection()->last()->getDisk(),
            $this->driver->fresh(),
            $this->driver->getMediaCollection()
        );
    }
}

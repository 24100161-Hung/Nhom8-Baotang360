<?php

namespace Tests\Feature\Public;

use App\Models\Artifact;
use App\Models\BlogPost;
use App\Models\Hotspot;
use App\Models\Interpretation;
use App\Models\MediaAsset;
use App\Models\Museum;
use App\Models\Scene;
use App\Models\TicketPackage;
use App\Models\Tour;
use App\Models\VisitDay;
use Database\Factories\MuseumFactory;

/** Dữ liệu tối thiểu cho các trang công khai: bảo tàng, 2 phòng nối nhau, hiện vật, tour 3 bước, gói vé, bài viết. */
trait BuildsMuseumContent
{
    protected Museum $museum;
    protected Scene $hall;
    protected Scene $dino;
    protected Artifact $trex;
    protected Tour $tour;

    protected function buildMuseum(): void
    {
        $this->museum = MuseumFactory::main();
        $this->museum->update(['floorplan' => ['image' => 'floorplan.svg', 'width' => 1000, 'height' => 600], 'address' => 'Darwinweg 2']);
        Interpretation::create(['subject_type' => 'museum', 'subject_id' => $this->museum->id, 'locale' => 'vi', 'depth_level' => 1,
            'title' => 'Naturalis', 'summary' => 'Bảo tàng', 'body' => 'Bảo tàng **lịch sử tự nhiên**']);
        $this->hall = Scene::factory()->create(['slug' => 'main-hall', 'code' => 'main_hall', 'sort_order' => 1]);
        $this->dino = Scene::factory()->create(['slug' => 'dinosaur-era', 'code' => 'dinosaur', 'sort_order' => 2,
            'tiles_path' => 'tiles/2', 'tile_config' => ['cubeResolution' => 2048, 'tileResolution' => 512, 'maxLevel' => 3, 'extension' => 'jpg'], 'tiles_state' => 'done']);
        $this->trex = Artifact::factory()->create(['slug' => 'trix-t-rex', 'scene_id' => $this->dino->id, 'is_highlight' => true,
            'material' => 'Hóa thạch xương', 'origin_country' => 'US', 'taxon_group' => 'dinosauria', 'scientific_name' => 'Tyrannosaurus rex']);
        Hotspot::factory()->create(['scene_id' => $this->hall->id, 'type' => 'scene', 'target_scene_id' => $this->dino->id]);
        Hotspot::factory()->create(['scene_id' => $this->dino->id, 'type' => 'artifact', 'artifact_id' => $this->trex->id]);
        MediaAsset::create(['subject_type' => 'scene', 'subject_id' => $this->hall->id, 'media_type' => 'audio', 'locale' => 'vi', 'license' => 'simulated']);
        $this->tour = Tour::factory()->withSteps(3, [$this->hall, $this->dino, $this->hall])->create(['slug' => 'dino-tour', 'is_featured' => true]);
        TicketPackage::factory()->regular()->create();
        TicketPackage::factory()->premium()->create();
        BlogPost::factory()->create(['slug' => 'trix-tro-ve']);
        VisitDay::factory()->on(today()->addDays(3)->toDateString())->create();
    }
}

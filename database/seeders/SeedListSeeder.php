<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SeedListSeeder extends Seeder
{
    /** @var list<array{url: string, name: string}> */
    private const SITES = [
        ['url' => 'https://openai.com', 'name' => 'OpenAI'],
        ['url' => 'https://anthropic.com', 'name' => 'Anthropic'],
        ['url' => 'https://midjourney.com', 'name' => 'Midjourney'],
        ['url' => 'https://stability.ai', 'name' => 'Stability AI'],
        ['url' => 'https://huggingface.co', 'name' => 'Hugging Face'],
        ['url' => 'https://jasper.ai', 'name' => 'Jasper'],
        ['url' => 'https://copy.ai', 'name' => 'Copy.ai'],
        ['url' => 'https://writesonic.com', 'name' => 'Writesonic'],
        ['url' => 'https://runwayml.com', 'name' => 'Runway'],
        ['url' => 'https://synthesia.io', 'name' => 'Synthesia'],
        ['url' => 'https://descript.com', 'name' => 'Descript'],
        ['url' => 'https://notion.so', 'name' => 'Notion'],
        ['url' => 'https://canva.com', 'name' => 'Canva'],
        ['url' => 'https://grammarly.com', 'name' => 'Grammarly'],
        ['url' => 'https://replit.com', 'name' => 'Replit'],
        ['url' => 'https://cursor.com', 'name' => 'Cursor'],
        ['url' => 'https://vercel.com', 'name' => 'Vercel'],
        ['url' => 'https://databricks.com', 'name' => 'Databricks'],
        ['url' => 'https://scale.ai', 'name' => 'Scale AI'],
        ['url' => 'https://cohere.com', 'name' => 'Cohere'],
        ['url' => 'https://perplexity.ai', 'name' => 'Perplexity'],
        ['url' => 'https://character.ai', 'name' => 'Character.AI'],
        ['url' => 'https://together.ai', 'name' => 'Together AI'],
        ['url' => 'https://mistral.ai', 'name' => 'Mistral AI'],
        ['url' => 'https://deepmind.google', 'name' => 'Google DeepMind'],
        ['url' => 'https://meta.ai', 'name' => 'Meta AI'],
        ['url' => 'https://nvidia.com/ai', 'name' => 'NVIDIA AI'],
        ['url' => 'https://suno.com', 'name' => 'Suno'],
        ['url' => 'https://elevenlabs.io', 'name' => 'ElevenLabs'],
        ['url' => 'https://pika.art', 'name' => 'Pika'],
    ];

    /**
     * Seed the database with a curated list of AI company sites.
     */
    public function run(): void
    {
        $created = 0;

        foreach (self::SITES as $siteData) {
            $domain = parse_url($siteData['url'], PHP_URL_HOST);

            if (Site::where('domain', $domain)->exists()) {
                continue;
            }

            Site::create([
                'url' => $siteData['url'],
                'domain' => $domain,
                'name' => $siteData['name'],
                'status' => 'queued',
                'source' => 'seed',
            ]);

            $created++;
        }

        $this->command?->info("Seeded {$created} new AI company sites.");
    }
}

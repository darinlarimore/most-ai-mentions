<?php

namespace App\Services;

use App\Models\Site;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteDiscoveryService
{
    /** @var list<string> */
    private const G2_CATEGORY_URLS = [
        'https://www.g2.com/categories/ai-writing-assistant',
        'https://www.g2.com/categories/artificial-intelligence',
        'https://www.g2.com/categories/ai-chatbots',
        'https://www.g2.com/categories/ai-code-generation',
        'https://www.g2.com/categories/ai-image-generator',
    ];

    private const PRODUCTHUNT_URLS = [
        'https://www.producthunt.com/topics/artificial-intelligence',
    ];

    private const HN_URLS = [
        'https://news.ycombinator.com/best',
        'https://news.ycombinator.com/',
    ];

    /** @var list<string> */
    private const AI_KEYWORDS = [
        'ai', 'artificial intelligence', 'machine learning', 'llm', 'gpt',
        'chatbot', 'generative', 'neural', 'deep learning', 'copilot',
        'diffusion', 'transformer', 'large language model',
    ];

    /** @var array<string, string> Curated popular sites likely to mention AI heavily (url => name) */
    private const POPULAR_SITES = [
        // ── AI Labs & Foundation Model Companies ──
        'https://openai.com' => 'OpenAI',
        'https://anthropic.com' => 'Anthropic',
        'https://deepmind.google' => 'Google DeepMind',
        'https://ai.meta.com' => 'Meta AI',
        'https://ai.google' => 'Google AI',
        'https://microsoft.com/ai' => 'Microsoft AI',
        'https://nvidia.com/ai' => 'NVIDIA AI',
        'https://stability.ai' => 'Stability AI',
        'https://mistral.ai' => 'Mistral AI',
        'https://cohere.com' => 'Cohere',
        'https://inflection.ai' => 'Inflection AI',
        'https://ai21.com' => 'AI21 Labs',
        'https://aleph-alpha.com' => 'Aleph Alpha',
        'https://reka.ai' => 'Reka AI',
        'https://adept.ai' => 'Adept',
        'https://characterai.com' => 'Character.AI',
        'https://xai.com' => 'xAI',
        'https://zhipuai.cn' => 'Zhipu AI',
        'https://01.ai' => 'Yi (01.AI)',
        'https://minimaxi.com' => 'MiniMax',

        // ── AI Image, Video & Audio Generation ──
        'https://midjourney.com' => 'Midjourney',
        'https://runway.ml' => 'Runway',
        'https://pika.art' => 'Pika',
        'https://klingai.com' => 'Kling AI',
        'https://lumalabs.ai' => 'Luma AI',
        'https://leonardo.ai' => 'Leonardo AI',
        'https://ideogram.ai' => 'Ideogram',
        'https://playgroundai.com' => 'Playground AI',
        'https://clipdrop.co' => 'Clipdrop',
        'https://nightcafe.studio' => 'NightCafe',
        'https://artbreeder.com' => 'Artbreeder',
        'https://deepai.org' => 'DeepAI',
        'https://stockimg.ai' => 'Stockimg AI',
        'https://photoroom.com' => 'PhotoRoom',
        'https://removebg.com' => 'Remove.bg',
        'https://topazlabs.com' => 'Topaz Labs',
        'https://elevenlabs.io' => 'ElevenLabs',
        'https://murf.ai' => 'Murf AI',
        'https://play.ht' => 'PlayHT',
        'https://resemble.ai' => 'Resemble AI',
        'https://speechify.com' => 'Speechify',
        'https://descript.com' => 'Descript',
        'https://synthesia.io' => 'Synthesia',
        'https://heygen.com' => 'HeyGen',
        'https://invideo.io' => 'InVideo',
        'https://pictory.ai' => 'Pictory',
        'https://suno.com' => 'Suno',
        'https://udio.com' => 'Udio',
        'https://aiva.ai' => 'AIVA',
        'https://boomy.com' => 'Boomy',
        'https://soundraw.io' => 'Soundraw',

        // ── AI Writing & Content ──
        'https://jasper.ai' => 'Jasper',
        'https://copy.ai' => 'Copy.ai',
        'https://writesonic.com' => 'Writesonic',
        'https://rytr.me' => 'Rytr',
        'https://writer.com' => 'Writer',
        'https://anyword.com' => 'Anyword',
        'https://wordtune.com' => 'Wordtune',
        'https://hyperwriteai.com' => 'HyperWrite',
        'https://sudowrite.com' => 'Sudowrite',
        'https://contentbot.ai' => 'ContentBot',
        'https://peppertype.ai' => 'Peppertype',
        'https://grammarly.com' => 'Grammarly',
        'https://quillbot.com' => 'QuillBot',
        'https://typeset.io' => 'SciSpace',
        'https://scalenut.com' => 'Scalenut',
        'https://frase.io' => 'Frase',
        'https://surfer.ai' => 'Surfer AI',
        'https://marketmuse.com' => 'MarketMuse',
        'https://clearscope.io' => 'Clearscope',

        // ── AI Search & Assistants ──
        'https://perplexity.ai' => 'Perplexity',
        'https://you.com' => 'You.com',
        'https://phind.com' => 'Phind',
        'https://kagi.com' => 'Kagi',
        'https://andi.search' => 'Andi Search',
        'https://brave.com/search/ai' => 'Brave AI Search',
        'https://pi.ai' => 'Pi',
        'https://poe.com' => 'Poe',
        'https://claude.ai' => 'Claude',
        'https://chat.openai.com' => 'ChatGPT',
        'https://gemini.google.com' => 'Gemini',
        'https://copilot.microsoft.com' => 'Microsoft Copilot',

        // ── AI Code & Developer Tools ──
        'https://cursor.com' => 'Cursor',
        'https://replit.com' => 'Replit',
        'https://github.com/features/copilot' => 'GitHub Copilot',
        'https://tabnine.com' => 'Tabnine',
        'https://codeium.com' => 'Codeium',
        'https://sourcegraph.com' => 'Sourcegraph',
        'https://codium.ai' => 'CodiumAI',
        'https://devin.ai' => 'Devin',
        'https://cognition.ai' => 'Cognition AI',
        'https://sweep.dev' => 'Sweep',
        'https://aider.chat' => 'Aider',
        'https://continue.dev' => 'Continue',
        'https://pieces.app' => 'Pieces',
        'https://blackbox.ai' => 'Blackbox AI',
        'https://phind.com' => 'Phind',
        'https://bolt.new' => 'Bolt',
        'https://v0.dev' => 'v0',
        'https://lovable.dev' => 'Lovable',
        'https://windsurf.com' => 'Windsurf',

        // ── AI Infrastructure & MLOps ──
        'https://huggingface.co' => 'Hugging Face',
        'https://replicate.com' => 'Replicate',
        'https://together.ai' => 'Together AI',
        'https://groq.com' => 'Groq',
        'https://fireworks.ai' => 'Fireworks AI',
        'https://anyscale.com' => 'Anyscale',
        'https://modal.com' => 'Modal',
        'https://baseten.co' => 'Baseten',
        'https://banana.dev' => 'Banana',
        'https://beam.cloud' => 'Beam',
        'https://runpod.io' => 'RunPod',
        'https://lambdalabs.com' => 'Lambda',
        'https://coreweave.com' => 'CoreWeave',
        'https://vast.ai' => 'Vast.ai',
        'https://fluidstack.io' => 'FluidStack',

        // ── AI Frameworks & Dev Libraries ──
        'https://langchain.com' => 'LangChain',
        'https://llamaindex.ai' => 'LlamaIndex',
        'https://haystack.deepset.ai' => 'Haystack',
        'https://semantic-kernel.com' => 'Semantic Kernel',
        'https://crewai.com' => 'CrewAI',
        'https://autogen.microsoft.com' => 'AutoGen',
        'https://dspy.ai' => 'DSPy',
        'https://lmstudio.ai' => 'LM Studio',
        'https://ollama.com' => 'Ollama',
        'https://jan.ai' => 'Jan',
        'https://vllm.ai' => 'vLLM',
        'https://mlc.ai' => 'MLC LLM',

        // ── Vector Databases & RAG ──
        'https://pinecone.io' => 'Pinecone',
        'https://weaviate.io' => 'Weaviate',
        'https://chromadb.dev' => 'Chroma',
        'https://qdrant.tech' => 'Qdrant',
        'https://milvus.io' => 'Milvus',
        'https://zilliz.com' => 'Zilliz',
        'https://turbopuffer.com' => 'Turbopuffer',
        'https://trychroma.com' => 'Chroma',
        'https://unstructured.io' => 'Unstructured',

        // ── AI Productivity & Business Tools ──
        'https://notion.so' => 'Notion',
        'https://canva.com' => 'Canva',
        'https://figma.com' => 'Figma',
        'https://adobe.com/sensei' => 'Adobe Sensei',
        'https://vercel.com' => 'Vercel',
        'https://supabase.com' => 'Supabase',
        'https://zapier.com/ai' => 'Zapier AI',
        'https://bardeen.ai' => 'Bardeen',
        'https://otter.ai' => 'Otter.ai',
        'https://fireflies.ai' => 'Fireflies.ai',
        'https://krisp.ai' => 'Krisp',
        'https://assemblyai.com' => 'AssemblyAI',
        'https://deepgram.com' => 'Deepgram',
        'https://rev.ai' => 'Rev AI',
        'https://beautiful.ai' => 'Beautiful.ai',
        'https://gamma.app' => 'Gamma',
        'https://tome.app' => 'Tome',
        'https://decktopus.com' => 'Decktopus',
        'https://rows.com' => 'Rows',
        'https://obviously.ai' => 'Obviously AI',
        'https://akkio.com' => 'Akkio',
        'https://reclaim.ai' => 'Reclaim AI',
        'https://clockwise.com' => 'Clockwise',
        'https://mem.ai' => 'Mem',
        'https://taskade.com' => 'Taskade',
        'https://clickup.com/ai' => 'ClickUp AI',

        // ── AI Marketing & Sales ──
        'https://hubspot.com/artificial-intelligence' => 'HubSpot AI',
        'https://drift.com' => 'Drift',
        'https://intercom.com' => 'Intercom',
        'https://gong.io' => 'Gong',
        'https://chorus.ai' => 'Chorus',
        'https://salesloft.com' => 'SalesLoft',
        'https://outreach.io' => 'Outreach',
        'https://apollo.io' => 'Apollo',
        'https://seamless.ai' => 'Seamless.AI',
        'https://lavender.ai' => 'Lavender',
        'https://regie.ai' => 'Regie.ai',
        'https://warmly.ai' => 'Warmly',
        'https://chatfuel.com' => 'Chatfuel',
        'https://manychat.com' => 'ManyChat',
        'https://tidio.com' => 'Tidio',
        'https://ada.cx' => 'Ada',
        'https://forethought.ai' => 'Forethought',

        // ── AI Data & Analytics ──
        'https://databricks.com' => 'Databricks',
        'https://snowflake.com' => 'Snowflake',
        'https://datadog.com' => 'Datadog',
        'https://scale.com' => 'Scale AI',
        'https://labelbox.com' => 'Labelbox',
        'https://weights-biases.com' => 'Weights & Biases',
        'https://neptune.ai' => 'Neptune.ai',
        'https://mlflow.org' => 'MLflow',
        'https://wandb.ai' => 'W&B',
        'https://comet.com' => 'Comet',
        'https://dagshub.com' => 'DagsHub',
        'https://dvc.org' => 'DVC',
        'https://greatexpectations.io' => 'Great Expectations',
        'https://prefect.io' => 'Prefect',
        'https://airbyte.com' => 'Airbyte',
        'https://hex.tech' => 'Hex',
        'https://deepnote.com' => 'Deepnote',
        'https://observable.com' => 'Observable',

        // ── AI Security & Trust ──
        'https://robust.ai' => 'Robust AI',
        'https://nightfall.ai' => 'Nightfall AI',
        'https://protect.ai' => 'Protect AI',
        'https://calypsoai.com' => 'CalypsoAI',
        'https://lakera.ai' => 'Lakera',
        'https://rebuff.ai' => 'Rebuff AI',
        'https://hiddenlayer.com' => 'HiddenLayer',
        'https://truera.com' => 'TruEra',

        // ── AI Healthcare & Science ──
        'https://tempus.com' => 'Tempus',
        'https://pathai.com' => 'PathAI',
        'https://insitro.com' => 'Insitro',
        'https://recursion.com' => 'Recursion',
        'https://isomorphiclabs.com' => 'Isomorphic Labs',
        'https://insilico.com' => 'Insilico Medicine',
        'https://atomwise.com' => 'Atomwise',
        'https://benevolent.com' => 'BenevolentAI',
        'https://viz.ai' => 'Viz.ai',

        // ── AI Chips & Hardware ──
        'https://cerebras.net' => 'Cerebras',
        'https://sambanova.ai' => 'SambaNova',
        'https://graphcore.ai' => 'Graphcore',
        'https://d-matrix.ai' => 'D-Matrix',
        'https://tenstorrent.com' => 'Tenstorrent',
        'https://hailo.ai' => 'Hailo',
        'https://mythic.ai' => 'Mythic',
        'https://esperanto.ai' => 'Esperanto Technologies',

        // ── AI Robotics & Autonomous ──
        'https://figure.ai' => 'Figure AI',
        'https://1x.tech' => '1X Technologies',
        'https://covariant.ai' => 'Covariant',
        'https://skydio.com' => 'Skydio',
        'https://waymo.com' => 'Waymo',
        'https://cruise.com' => 'Cruise',
        'https://nuro.ai' => 'Nuro',
        'https://aurora.tech' => 'Aurora',
        'https://tesla.com/ai' => 'Tesla AI',
        'https://bostondynamics.com' => 'Boston Dynamics',

        // ── AI Education & Research ──
        'https://khanacademy.org' => 'Khan Academy',
        'https://coursera.org' => 'Coursera',
        'https://fast.ai' => 'fast.ai',
        'https://deeplearning.ai' => 'DeepLearning.AI',
        'https://kaggle.com' => 'Kaggle',
        'https://paperswithcode.com' => 'Papers With Code',
        'https://arxiv.org' => 'arXiv',
        'https://semanticscholar.org' => 'Semantic Scholar',
        'https://connectedpapers.com' => 'Connected Papers',
        'https://elicit.com' => 'Elicit',
        'https://consensus.app' => 'Consensus',
        'https://scholarcy.com' => 'Scholarcy',

        // ── AI News & Community ──
        'https://techcrunch.com/category/artificial-intelligence' => 'TechCrunch AI',
        'https://theverge.com/ai-artificial-intelligence' => 'The Verge AI',
        'https://wired.com/tag/artificial-intelligence' => 'WIRED AI',
        'https://arstechnica.com/ai' => 'Ars Technica AI',
        'https://venturebeat.com/ai' => 'VentureBeat AI',
        'https://thenextweb.com/topic/artificial-intelligence' => 'TNW AI',
        'https://aiweirdness.com' => 'AI Weirdness',
        'https://bensbites.com' => "Ben's Bites",
        'https://therundown.ai' => 'The Rundown AI',
        'https://theaivalley.com' => 'The AI Valley',
        'https://artificialintelligence-news.com' => 'AI News',
        'https://unite.ai' => 'Unite.AI',
        'https://marktechpost.com' => 'MarkTechPost',
        'https://analyticsvidhya.com' => 'Analytics Vidhya',
        'https://towardsdatascience.com' => 'Towards Data Science',
        'https://machinelearningmastery.com' => 'Machine Learning Mastery',
        'https://theneurondaily.com' => 'The Neuron',
        'https://superhuman.beehiiv.com' => 'Superhuman AI',

        // ── Enterprise AI ──
        'https://salesforce.com/artificial-intelligence' => 'Salesforce AI',
        'https://ibm.com/watson' => 'IBM Watson',
        'https://oracle.com/artificial-intelligence' => 'Oracle AI',
        'https://sap.com/products/artificial-intelligence.html' => 'SAP AI',
        'https://servicenow.com/ai' => 'ServiceNow AI',
        'https://palantir.com' => 'Palantir',
        'https://c3.ai' => 'C3.ai',
        'https://h2o.ai' => 'H2O.ai',
        'https://datarobot.com' => 'DataRobot',
        'https://domino.ai' => 'Domino Data Lab',
        'https://abacus.ai' => 'Abacus.AI',
        'https://nvidiaomniverse.com' => 'NVIDIA Omniverse',

        // ── AI Design & Creative ──
        'https://magician.design' => 'Magician',
        'https://uizard.io' => 'Uizard',
        'https://framer.com' => 'Framer',
        'https://looka.com' => 'Looka',
        'https://brandmark.io' => 'Brandmark',
        'https://designs.ai' => 'Designs.ai',
        'https://kittl.com' => 'Kittl',
        'https://recraft.ai' => 'Recraft',

        // ── AI Legal, Finance & HR ──
        'https://harvey.ai' => 'Harvey AI',
        'https://casetext.com' => 'Casetext',
        'https://robin-ai.com' => 'Robin AI',
        'https://brightwave.io' => 'Brightwave',
        'https://kensho.com' => 'Kensho',
        'https://alphasense.com' => 'AlphaSense',
        'https://eightfold.ai' => 'Eightfold AI',
        'https://beamery.com' => 'Beamery',
        'https://textio.com' => 'Textio',

        // ── Popular Tech Sites That Hype AI ──
        'https://shopify.com/ai' => 'Shopify AI',
        'https://stripe.com' => 'Stripe',
        'https://twilio.com' => 'Twilio',
        'https://cloudflare.com' => 'Cloudflare',
        'https://netlify.com' => 'Netlify',
        'https://digitalocean.com' => 'DigitalOcean',
        'https://linode.com' => 'Linode',
        'https://fly.io' => 'Fly.io',
        'https://railway.app' => 'Railway',
        'https://render.com' => 'Render',
        'https://planetscale.com' => 'PlanetScale',
        'https://neon.tech' => 'Neon',
        'https://turso.tech' => 'Turso',
        'https://upstash.com' => 'Upstash',
        'https://convex.dev' => 'Convex',
        'https://sanity.io' => 'Sanity',
        'https://contentful.com' => 'Contentful',
        'https://segment.com' => 'Segment',
        'https://amplitude.com' => 'Amplitude',
        'https://mixpanel.com' => 'Mixpanel',
        'https://posthog.com' => 'PostHog',
        'https://linear.app' => 'Linear',
        'https://slack.com' => 'Slack',
        'https://zoom.us' => 'Zoom',
        'https://asana.com' => 'Asana',
        'https://monday.com' => 'Monday.com',
        'https://airtable.com' => 'Airtable',
        'https://coda.io' => 'Coda',
        'https://miro.com' => 'Miro',
        'https://loom.com' => 'Loom',
        'https://calendly.com' => 'Calendly',
        'https://webflow.com' => 'Webflow',
        'https://bubble.io' => 'Bubble',
        'https://retool.com' => 'Retool',
        'https://appsmith.com' => 'Appsmith',
    ];

    /** @var list<string> Domains to skip (social media, generic, etc.) */
    private const EXCLUDED_DOMAINS = [
        'google.com', 'youtube.com', 'twitter.com', 'x.com', 'facebook.com',
        'linkedin.com', 'reddit.com', 'github.com', 'wikipedia.org',
        'news.ycombinator.com', 'g2.com', 'producthunt.com', 'amazonaws.com',
        'cloudfront.net', 'archive.org', 'web.archive.org',
    ];

    /**
     * Run all discovery sources and return total new sites added.
     */
    public function discoverAll(): int
    {
        $total = 0;

        $total += $this->discoverPopular()->count();
        $total += $this->discoverFromG2()->count();
        $total += $this->discoverFromProductHunt()->count();
        $total += $this->discoverFromHackerNews()->count();

        return $total;
    }

    /**
     * Add curated popular AI sites that are known to mention AI heavily.
     *
     * @return Collection<int, Site>
     */
    public function discoverPopular(): Collection
    {
        $created = collect();

        $existingDomains = Site::pluck('domain')->toArray();

        foreach (self::POPULAR_SITES as $url => $name) {
            $domain = parse_url($url, PHP_URL_HOST);

            if (! $domain || in_array($domain, $existingDomains)) {
                continue;
            }

            try {
                $site = Site::create([
                    'url' => $url,
                    'domain' => $domain,
                    'name' => $name,
                    'status' => 'queued',
                    'source' => 'curated',
                ]);

                $created->push($site);
                $existingDomains[] = $domain;
            } catch (\Throwable $e) {
                Log::debug("SiteDiscovery: Skipped {$url}", ['error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Discover AI software sites from G2 category pages.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromG2(): Collection
    {
        $urls = [];

        foreach (self::G2_CATEGORY_URLS as $categoryUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($categoryUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractG2ProductUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch G2 page {$categoryUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'g2');
    }

    /**
     * Discover AI sites from ProductHunt topics.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromProductHunt(): Collection
    {
        $urls = [];

        foreach (self::PRODUCTHUNT_URLS as $topicUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($topicUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractProductHuntUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch ProductHunt page {$topicUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'producthunt');
    }

    /**
     * Discover AI-related sites from Hacker News.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromHackerNews(): Collection
    {
        $urls = [];

        foreach (self::HN_URLS as $hnUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($hnUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractHackerNewsUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch HN page {$hnUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'hackernews');
    }

    /**
     * Extract product website URLs from G2 category HTML.
     *
     * @return list<string>
     */
    private function extractG2ProductUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // G2 product cards link to product pages with data-href or href attributes
        $links = $xpath->query('//a[contains(@href, "/products/")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if (str_contains($href, '/products/') && ! str_contains($href, '/reviews')) {
                    // G2 product pages often contain the website URL; for now collect the product name from the URL
                    // and we'll try to extract the actual website from the product page
                    $productName = $this->extractG2ProductName($href);

                    if ($productName) {
                        $urls[] = "https://{$productName}.com";
                    }
                }
            }
        }

        // Also look for direct outbound links to product websites
        $outboundLinks = $xpath->query('//a[contains(@class, "website") or contains(@data-action, "visit")]');

        if ($outboundLinks) {
            foreach ($outboundLinks as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Extract product URLs from ProductHunt HTML.
     *
     * @return list<string>
     */
    private function extractProductHuntUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // ProductHunt posts have links to external product websites
        $links = $xpath->query('//a[contains(@href, "http")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Extract AI-related URLs from Hacker News HTML.
     *
     * @return list<string>
     */
    private function extractHackerNewsUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // HN story links are in <span class="titleline"><a href="...">Title</a></span>
        $storyLinks = $xpath->query('//span[contains(@class, "titleline")]/a');

        if ($storyLinks) {
            foreach ($storyLinks as $link) {
                $href = $link->getAttribute('href');
                $title = strtolower($link->textContent ?? '');

                // Only include links whose titles contain AI-related keywords
                $isAiRelated = false;

                foreach (self::AI_KEYWORDS as $keyword) {
                    if (str_contains($title, $keyword)) {
                        $isAiRelated = true;

                        break;
                    }
                }

                if ($isAiRelated && $this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Create Site records from a list of discovered URLs, skipping duplicates.
     *
     * @param  list<string>  $urls
     * @return Collection<int, Site>
     */
    private function createSitesFromUrls(array $urls, string $source): Collection
    {
        $created = collect();

        $normalizedUrls = collect($urls)
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->filter()
            ->unique();

        // Get existing domains to skip
        $existingDomains = Site::whereIn('domain', $normalizedUrls->map(
            fn (string $url) => parse_url($url, PHP_URL_HOST)
        )->filter())->pluck('domain')->toArray();

        foreach ($normalizedUrls as $url) {
            $domain = parse_url($url, PHP_URL_HOST);

            if (! $domain || in_array($domain, $existingDomains)) {
                continue;
            }

            if ($this->isExcludedDomain($domain)) {
                continue;
            }

            try {
                $site = Site::create([
                    'url' => $url,
                    'domain' => $domain,
                    'name' => $this->domainToName($domain),
                    'status' => 'queued',
                    'source' => $source,
                ]);

                $created->push($site);
                $existingDomains[] = $domain;
            } catch (\Throwable $e) {
                // Likely a duplicate URL constraint violation, skip
                Log::debug("SiteDiscovery: Skipped {$url}", ['error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Normalize a URL to its homepage.
     */
    public function normalizeUrl(string $url): ?string
    {
        // Ensure URL has a scheme
        if (! str_starts_with($url, 'http')) {
            $url = "https://{$url}";
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        // Strip www prefix for normalization
        $host = preg_replace('/^www\./', '', $host);

        return "https://{$host}";
    }

    /**
     * Check if a URL is a valid external URL worth crawling.
     */
    private function isValidExternalUrl(string $url): bool
    {
        if (! str_starts_with($url, 'http')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        return ! $this->isExcludedDomain($host);
    }

    /**
     * Check if a domain should be excluded.
     */
    private function isExcludedDomain(string $domain): bool
    {
        $domain = preg_replace('/^www\./', '', $domain);

        foreach (self::EXCLUDED_DOMAINS as $excluded) {
            if ($domain === $excluded || str_ends_with($domain, ".{$excluded}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract product name from a G2 product URL.
     */
    private function extractG2ProductName(string $href): ?string
    {
        if (preg_match('#/products/([a-z0-9-]+)#i', $href, $matches)) {
            return str_replace('-', '', $matches[1]);
        }

        return null;
    }

    /**
     * Convert a domain to a human-readable name.
     */
    private function domainToName(string $domain): string
    {
        $name = preg_replace('/^www\./', '', $domain);
        // Remove TLD
        $name = preg_replace('/\.[a-z]{2,}$/i', '', $name);
        // Remove secondary TLD (e.g. .co from .co.uk)
        $name = preg_replace('/\.[a-z]{2,}$/i', '', $name);
        // Convert hyphens/dots to spaces and title-case
        $name = str_replace(['-', '.'], ' ', $name);

        return ucwords($name);
    }

    /**
     * Parse HTML string into a DOMDocument.
     */
    private function parseHtml(string $html): ?DOMDocument
    {
        if (empty($html)) {
            return null;
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $doc;
    }

    /**
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (compatible; MostAIMentions/1.0; +https://mostai.mentions)',
            'Accept' => 'text/html,application/xhtml+xml',
        ];
    }
}

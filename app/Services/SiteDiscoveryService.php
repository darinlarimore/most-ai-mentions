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
    private const G2_BROAD_CATEGORY_URLS = [
        'https://www.g2.com/categories/crm',
        'https://www.g2.com/categories/project-management',
        'https://www.g2.com/categories/accounting',
        'https://www.g2.com/categories/email-marketing',
        'https://www.g2.com/categories/help-desk',
        'https://www.g2.com/categories/video-conferencing',
        'https://www.g2.com/categories/e-commerce-platforms',
        'https://www.g2.com/categories/social-media-management',
        'https://www.g2.com/categories/hr-management-suites',
        'https://www.g2.com/categories/website-builder',
    ];

    private const DOWNDETECTOR_URLS = [
        'https://downdetector.com/trending/',
        'https://downdetector.com/companies/',
    ];

    private const TRANCO_LIST_URL = 'https://tranco-list.eu/top-1m.csv.zip';

    /** @var list<string> */
    private const AWWWARDS_URLS = [
        'https://www.awwwards.com/websites/',
        'https://www.awwwards.com/websites/sites-of-the-day/',
        'https://www.awwwards.com/websites/nominees/',
    ];

    /** @var list<string> */
    private const CAPTERRA_CATEGORY_URLS = [
        'https://www.capterra.com/project-management-software/',
        'https://www.capterra.com/crm-software/',
        'https://www.capterra.com/accounting-software/',
        'https://www.capterra.com/email-marketing-software/',
        'https://www.capterra.com/help-desk-software/',
        'https://www.capterra.com/artificial-intelligence-software/',
        'https://www.capterra.com/video-conferencing-software/',
        'https://www.capterra.com/learning-management-system-software/',
    ];

    /** @var list<string> */
    private const ALTERNATIVETO_URLS = [
        'https://alternativeto.net/browse/popular/',
        'https://alternativeto.net/browse/trending/',
        'https://alternativeto.net/category/developer-tools/',
        'https://alternativeto.net/category/social/',
        'https://alternativeto.net/category/business-and-commerce/',
    ];

    /** @var list<string> */
    private const BUILTWITH_URLS = [
        'https://builtwith.com/top-sites',
        'https://builtwith.com/websites/application-framework',
    ];

    /** @var list<string> */
    private const SIMILARWEB_URLS = [
        'https://www.similarweb.com/top-websites/',
        'https://www.similarweb.com/top-websites/computers-electronics-and-technology/',
        'https://www.similarweb.com/top-websites/business-and-consumer-services/',
    ];

    /** @var list<string> */
    private const STACKSHARE_URLS = [
        'https://stackshare.io/tools/trending',
        'https://stackshare.io/tools/top',
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

        // ── Major Tech / FAANG / Big Companies ──
        'https://apple.com' => 'Apple',
        'https://amazon.com' => 'Amazon',
        'https://microsoft.com' => 'Microsoft',
        'https://cloud.google.com' => 'Google Cloud',
        'https://aws.amazon.com' => 'AWS',
        'https://azure.microsoft.com' => 'Microsoft Azure',
        'https://intel.com' => 'Intel',
        'https://amd.com' => 'AMD',
        'https://qualcomm.com' => 'Qualcomm',
        'https://arm.com' => 'Arm',
        'https://broadcom.com' => 'Broadcom',
        'https://dell.com' => 'Dell',
        'https://hp.com' => 'HP',
        'https://lenovo.com' => 'Lenovo',
        'https://cisco.com' => 'Cisco',
        'https://vmware.com' => 'VMware',
        'https://redhat.com' => 'Red Hat',
        'https://docker.com' => 'Docker',
        'https://hashicorp.com' => 'HashiCorp',
        'https://elastic.co' => 'Elastic',
        'https://splunk.com' => 'Splunk',
        'https://newrelic.com' => 'New Relic',
        'https://pagerduty.com' => 'PagerDuty',
        'https://atlassian.com' => 'Atlassian',
        'https://jetbrains.com' => 'JetBrains',
        'https://unity.com' => 'Unity',
        'https://epicgames.com' => 'Epic Games',
        'https://samsung.com' => 'Samsung',
        'https://sony.com' => 'Sony',
        'https://siemens.com' => 'Siemens',
        'https://ge.com' => 'GE',
        'https://honeywell.com' => 'Honeywell',
        'https://accenture.com' => 'Accenture',
        'https://deloitte.com' => 'Deloitte',
        'https://mckinsey.com' => 'McKinsey',
        'https://bcg.com' => 'BCG',

        // ── Social Media & Messaging ──
        'https://instagram.com' => 'Instagram',
        'https://tiktok.com' => 'TikTok',
        'https://snapchat.com' => 'Snapchat',
        'https://pinterest.com' => 'Pinterest',
        'https://discord.com' => 'Discord',
        'https://telegram.org' => 'Telegram',
        'https://signal.org' => 'Signal',
        'https://whatsapp.com' => 'WhatsApp',
        'https://mastodon.social' => 'Mastodon',
        'https://threads.net' => 'Threads',
        'https://bluesky.social' => 'Bluesky',
        'https://tumblr.com' => 'Tumblr',
        'https://twitch.tv' => 'Twitch',
        'https://kick.com' => 'Kick',
        'https://bereal.com' => 'BeReal',

        // ── Streaming & Entertainment ──
        'https://netflix.com' => 'Netflix',
        'https://disneyplus.com' => 'Disney+',
        'https://hulu.com' => 'Hulu',
        'https://max.com' => 'Max (HBO)',
        'https://peacocktv.com' => 'Peacock',
        'https://paramountplus.com' => 'Paramount+',
        'https://primevideo.com' => 'Prime Video',
        'https://crunchyroll.com' => 'Crunchyroll',
        'https://spotify.com' => 'Spotify',
        'https://music.apple.com' => 'Apple Music',
        'https://tidal.com' => 'Tidal',
        'https://deezer.com' => 'Deezer',
        'https://soundcloud.com' => 'SoundCloud',
        'https://pandora.com' => 'Pandora',
        'https://audible.com' => 'Audible',

        // ── E-Commerce & Retail ──
        'https://ebay.com' => 'eBay',
        'https://etsy.com' => 'Etsy',
        'https://walmart.com' => 'Walmart',
        'https://target.com' => 'Target',
        'https://bestbuy.com' => 'Best Buy',
        'https://costco.com' => 'Costco',
        'https://homedepot.com' => 'Home Depot',
        'https://lowes.com' => 'Lowes',
        'https://wayfair.com' => 'Wayfair',
        'https://aliexpress.com' => 'AliExpress',
        'https://temu.com' => 'Temu',
        'https://shein.com' => 'Shein',
        'https://zappos.com' => 'Zappos',
        'https://nordstrom.com' => 'Nordstrom',
        'https://macys.com' => 'Macys',
        'https://nike.com' => 'Nike',
        'https://adidas.com' => 'Adidas',
        'https://zara.com' => 'Zara',
        'https://hm.com' => 'H&M',
        'https://ikea.com' => 'IKEA',
        'https://chewy.com' => 'Chewy',
        'https://instacart.com' => 'Instacart',
        'https://wish.com' => 'Wish',
        'https://mercadolibre.com' => 'MercadoLibre',
        'https://rakuten.com' => 'Rakuten',

        // ── Food & Delivery ──
        'https://doordash.com' => 'DoorDash',
        'https://ubereats.com' => 'Uber Eats',
        'https://grubhub.com' => 'Grubhub',
        'https://postmates.com' => 'Postmates',
        'https://seamless.com' => 'Seamless',
        'https://gopuff.com' => 'Gopuff',
        'https://starbucks.com' => 'Starbucks',
        'https://mcdonalds.com' => 'McDonalds',
        'https://chipotle.com' => 'Chipotle',
        'https://dominos.com' => 'Dominos',

        // ── Ride-Hailing & Travel ──
        'https://uber.com' => 'Uber',
        'https://lyft.com' => 'Lyft',
        'https://airbnb.com' => 'Airbnb',
        'https://booking.com' => 'Booking.com',
        'https://expedia.com' => 'Expedia',
        'https://tripadvisor.com' => 'TripAdvisor',
        'https://kayak.com' => 'Kayak',
        'https://skyscanner.com' => 'Skyscanner',
        'https://hotels.com' => 'Hotels.com',
        'https://vrbo.com' => 'Vrbo',
        'https://delta.com' => 'Delta',
        'https://united.com' => 'United Airlines',
        'https://aa.com' => 'American Airlines',
        'https://southwest.com' => 'Southwest Airlines',

        // ── Banking & Finance ──
        'https://chase.com' => 'Chase',
        'https://bankofamerica.com' => 'Bank of America',
        'https://wellsfargo.com' => 'Wells Fargo',
        'https://citi.com' => 'Citi',
        'https://capitalone.com' => 'Capital One',
        'https://discover.com' => 'Discover',
        'https://americanexpress.com' => 'American Express',
        'https://usbank.com' => 'US Bank',
        'https://schwab.com' => 'Charles Schwab',
        'https://fidelity.com' => 'Fidelity',
        'https://vanguard.com' => 'Vanguard',
        'https://tdameritrade.com' => 'TD Ameritrade',
        'https://robinhood.com' => 'Robinhood',
        'https://coinbase.com' => 'Coinbase',
        'https://binance.com' => 'Binance',
        'https://kraken.com' => 'Kraken',
        'https://paypal.com' => 'PayPal',
        'https://venmo.com' => 'Venmo',
        'https://cashapp.com' => 'Cash App',
        'https://wise.com' => 'Wise',
        'https://revolut.com' => 'Revolut',
        'https://chime.com' => 'Chime',
        'https://sofi.com' => 'SoFi',
        'https://plaid.com' => 'Plaid',
        'https://brex.com' => 'Brex',
        'https://ramp.com' => 'Ramp',
        'https://mercury.com' => 'Mercury',

        // ── Telecom & ISPs ──
        'https://att.com' => 'AT&T',
        'https://verizon.com' => 'Verizon',
        'https://t-mobile.com' => 'T-Mobile',
        'https://xfinity.com' => 'Xfinity',
        'https://spectrum.com' => 'Spectrum',
        'https://cox.com' => 'Cox',
        'https://dish.com' => 'Dish',
        'https://visible.com' => 'Visible',
        'https://mint-mobile.com' => 'Mint Mobile',
        'https://starlink.com' => 'Starlink',

        // ── Gaming ──
        'https://store.steampowered.com' => 'Steam',
        'https://store.epicgames.com' => 'Epic Games Store',
        'https://xbox.com' => 'Xbox',
        'https://playstation.com' => 'PlayStation',
        'https://nintendo.com' => 'Nintendo',
        'https://roblox.com' => 'Roblox',
        'https://ea.com' => 'EA',
        'https://activision.com' => 'Activision',
        'https://riotgames.com' => 'Riot Games',
        'https://blizzard.com' => 'Blizzard',
        'https://ubisoft.com' => 'Ubisoft',
        'https://valvesoftware.com' => 'Valve',
        'https://supercell.com' => 'Supercell',
        'https://mihoyo.com' => 'HoYoverse',

        // ── News & Media ──
        'https://cnn.com' => 'CNN',
        'https://bbc.com' => 'BBC',
        'https://nytimes.com' => 'New York Times',
        'https://washingtonpost.com' => 'Washington Post',
        'https://wsj.com' => 'Wall Street Journal',
        'https://reuters.com' => 'Reuters',
        'https://apnews.com' => 'AP News',
        'https://bloomberg.com' => 'Bloomberg',
        'https://forbes.com' => 'Forbes',
        'https://fortune.com' => 'Fortune',
        'https://businessinsider.com' => 'Business Insider',
        'https://cnbc.com' => 'CNBC',
        'https://theguardian.com' => 'The Guardian',
        'https://bbc.co.uk' => 'BBC UK',
        'https://economist.com' => 'The Economist',
        'https://ft.com' => 'Financial Times',
        'https://time.com' => 'TIME',
        'https://theatlantic.com' => 'The Atlantic',
        'https://newyorker.com' => 'The New Yorker',
        'https://vice.com' => 'Vice',
        'https://vox.com' => 'Vox',
        'https://buzzfeed.com' => 'BuzzFeed',
        'https://huffpost.com' => 'HuffPost',
        'https://usatoday.com' => 'USA Today',
        'https://foxnews.com' => 'Fox News',
        'https://nbcnews.com' => 'NBC News',
        'https://abcnews.go.com' => 'ABC News',
        'https://cbsnews.com' => 'CBS News',
        'https://npr.org' => 'NPR',
        'https://axios.com' => 'Axios',
        'https://politico.com' => 'Politico',
        'https://thehill.com' => 'The Hill',

        // ── Health & Wellness ──
        'https://webmd.com' => 'WebMD',
        'https://mayoclinic.org' => 'Mayo Clinic',
        'https://clevelandclinic.org' => 'Cleveland Clinic',
        'https://healthline.com' => 'Healthline',
        'https://zocdoc.com' => 'Zocdoc',
        'https://teladoc.com' => 'Teladoc',
        'https://onemedical.com' => 'One Medical',
        'https://fitbit.com' => 'Fitbit',
        'https://peloton.com' => 'Peloton',
        'https://noom.com' => 'Noom',
        'https://calm.com' => 'Calm',
        'https://headspace.com' => 'Headspace',

        // ── Real Estate ──
        'https://zillow.com' => 'Zillow',
        'https://redfin.com' => 'Redfin',
        'https://realtor.com' => 'Realtor.com',
        'https://trulia.com' => 'Trulia',
        'https://apartments.com' => 'Apartments.com',
        'https://opendoor.com' => 'Opendoor',
        'https://compass.com' => 'Compass',

        // ── Insurance ──
        'https://geico.com' => 'Geico',
        'https://progressive.com' => 'Progressive',
        'https://statefarm.com' => 'State Farm',
        'https://allstate.com' => 'Allstate',
        'https://lemonade.com' => 'Lemonade',
        'https://root.com' => 'Root Insurance',
        'https://oscar.com' => 'Oscar Health',

        // ── HR & Recruiting ──
        'https://indeed.com' => 'Indeed',
        'https://glassdoor.com' => 'Glassdoor',
        'https://lever.co' => 'Lever',
        'https://greenhouse.io' => 'Greenhouse',
        'https://workday.com' => 'Workday',
        'https://adp.com' => 'ADP',
        'https://gusto.com' => 'Gusto',
        'https://rippling.com' => 'Rippling',
        'https://deel.com' => 'Deel',
        'https://remote.com' => 'Remote',
        'https://lattice.com' => 'Lattice',
        'https://bamboohr.com' => 'BambooHR',

        // ── Cybersecurity ──
        'https://crowdstrike.com' => 'CrowdStrike',
        'https://paloaltonetworks.com' => 'Palo Alto Networks',
        'https://fortinet.com' => 'Fortinet',
        'https://zscaler.com' => 'Zscaler',
        'https://sentinelone.com' => 'SentinelOne',
        'https://cloudflare.com/security' => 'Cloudflare Security',
        'https://1password.com' => '1Password',
        'https://bitwarden.com' => 'Bitwarden',
        'https://lastpass.com' => 'LastPass',
        'https://dashlane.com' => 'Dashlane',
        'https://nordvpn.com' => 'NordVPN',
        'https://expressvpn.com' => 'ExpressVPN',
        'https://protonvpn.com' => 'Proton VPN',
        'https://proton.me' => 'Proton',
        'https://snyk.io' => 'Snyk',
        'https://okta.com' => 'Okta',
        'https://auth0.com' => 'Auth0',

        // ── Education ──
        'https://udemy.com' => 'Udemy',
        'https://skillshare.com' => 'Skillshare',
        'https://masterclass.com' => 'MasterClass',
        'https://edx.org' => 'edX',
        'https://udacity.com' => 'Udacity',
        'https://codecademy.com' => 'Codecademy',
        'https://pluralsight.com' => 'Pluralsight',
        'https://treehouse.com' => 'Treehouse',
        'https://brilliant.org' => 'Brilliant',
        'https://duolingo.com' => 'Duolingo',
        'https://quizlet.com' => 'Quizlet',
        'https://chegg.com' => 'Chegg',
        'https://brainly.com' => 'Brainly',
        'https://notion.so/education' => 'Notion for Education',

        // ── Automotive ──
        'https://tesla.com' => 'Tesla',
        'https://rivian.com' => 'Rivian',
        'https://lucidmotors.com' => 'Lucid Motors',
        'https://ford.com' => 'Ford',
        'https://gm.com' => 'GM',
        'https://toyota.com' => 'Toyota',
        'https://bmw.com' => 'BMW',
        'https://mercedes-benz.com' => 'Mercedes-Benz',
        'https://carvana.com' => 'Carvana',
        'https://carmax.com' => 'CarMax',

        // ── Crypto & Web3 ──
        'https://ethereum.org' => 'Ethereum',
        'https://solana.com' => 'Solana',
        'https://polygon.technology' => 'Polygon',
        'https://chain.link' => 'Chainlink',
        'https://opensea.io' => 'OpenSea',
        'https://uniswap.org' => 'Uniswap',
        'https://aave.com' => 'Aave',
        'https://lido.fi' => 'Lido',
        'https://alchemy.com' => 'Alchemy',
        'https://infura.io' => 'Infura',
        'https://thirdweb.com' => 'Thirdweb',

        // ── Miscellaneous Popular Services ──
        'https://yelp.com' => 'Yelp',
        'https://nextdoor.com' => 'Nextdoor',
        'https://medium.com' => 'Medium',
        'https://substack.com' => 'Substack',
        'https://ghost.org' => 'Ghost',
        'https://wordpress.com' => 'WordPress.com',
        'https://squarespace.com' => 'Squarespace',
        'https://wix.com' => 'Wix',
        'https://godaddy.com' => 'GoDaddy',
        'https://namecheap.com' => 'Namecheap',
        'https://mailchimp.com' => 'Mailchimp',
        'https://sendgrid.com' => 'SendGrid',
        'https://twilio.com/sendgrid' => 'Twilio SendGrid',
        'https://dropbox.com' => 'Dropbox',
        'https://box.com' => 'Box',
        'https://onedrive.live.com' => 'OneDrive',
        'https://drive.google.com' => 'Google Drive',
        'https://icloud.com' => 'iCloud',
        'https://evernote.com' => 'Evernote',
        'https://todoist.com' => 'Todoist',
        'https://trello.com' => 'Trello',
        'https://basecamp.com' => 'Basecamp',
        'https://jira.atlassian.com' => 'Jira',
        'https://freshworks.com' => 'Freshworks',
        'https://zendesk.com' => 'Zendesk',
        'https://helpscout.com' => 'Help Scout',
        'https://docusign.com' => 'DocuSign',
        'https://pandadoc.com' => 'PandaDoc',
        'https://typeform.com' => 'Typeform',
        'https://surveymonkey.com' => 'SurveyMonkey',
        'https://calendly.com/ai' => 'Calendly AI',
        'https://hootsuite.com' => 'Hootsuite',
        'https://buffer.com' => 'Buffer',
        'https://later.com' => 'Later',
        'https://sproutsocial.com' => 'Sprout Social',
        'https://canva.com/ai' => 'Canva AI',
        'https://grammarly.com/ai' => 'Grammarly AI',
        'https://semrush.com' => 'Semrush',
        'https://ahrefs.com' => 'Ahrefs',
        'https://moz.com' => 'Moz',
        'https://similarweb.com' => 'SimilarWeb',
        'https://hotjar.com' => 'Hotjar',
        'https://crazyegg.com' => 'Crazy Egg',
        'https://optimizely.com' => 'Optimizely',
        'https://launchdarkly.com' => 'LaunchDarkly',
        'https://split.io' => 'Split',
        'https://sentry.io' => 'Sentry',
        'https://bugsnag.com' => 'Bugsnag',
        'https://circleci.com' => 'CircleCI',
        'https://github.com/features/actions' => 'GitHub Actions',
        'https://gitlab.com' => 'GitLab',
        'https://bitbucket.org' => 'Bitbucket',
        'https://vercel.com/ai' => 'Vercel AI',
        'https://deno.com' => 'Deno',
        'https://bun.sh' => 'Bun',
        'https://astro.build' => 'Astro',
        'https://nextjs.org' => 'Next.js',
        'https://nuxt.com' => 'Nuxt',
        'https://svelte.dev' => 'Svelte',
        'https://vuejs.org' => 'Vue.js',
        'https://react.dev' => 'React',
        'https://angular.dev' => 'Angular',
        'https://laravel.com' => 'Laravel',
        'https://djangoproject.com' => 'Django',
        'https://rubyonrails.org' => 'Ruby on Rails',
        'https://spring.io' => 'Spring',
        'https://dotnet.microsoft.com' => '.NET',
        'https://rust-lang.org' => 'Rust',
        'https://go.dev' => 'Go',
        'https://python.org' => 'Python',
        'https://nodejs.org' => 'Node.js',
        'https://kotlinlang.org' => 'Kotlin',
        'https://swift.org' => 'Swift',
        'https://flutter.dev' => 'Flutter',
        'https://reactnative.dev' => 'React Native',
        'https://expo.dev' => 'Expo',
        'https://tauri.app' => 'Tauri',
        'https://electronjs.org' => 'Electron',
    ];

    /** @var list<string> Domains to skip (social media, generic, etc.) */
    private const EXCLUDED_DOMAINS = [
        'google.com', 'youtube.com', 'twitter.com', 'x.com', 'facebook.com',
        'linkedin.com', 'reddit.com', 'github.com', 'wikipedia.org',
        'news.ycombinator.com', 'g2.com', 'producthunt.com', 'amazonaws.com',
        'cloudfront.net', 'archive.org', 'web.archive.org',
        'awwwards.com', 'capterra.com', 'alternativeto.net', 'builtwith.com',
        'similarweb.com', 'stackshare.io',
    ];

    /**
     * Run all discovery sources and return total new sites added.
     */
    public function discoverAll(): int
    {
        $total = 0;

        $total += $this->discoverPopular()->count();
        $total += $this->discoverFromG2()->count();
        $total += $this->discoverFromG2Broad()->count();
        $total += $this->discoverFromProductHunt()->count();
        $total += $this->discoverFromHackerNews()->count();
        $total += $this->discoverFromDowndetector()->count();
        $total += $this->discoverFromTrancoList()->count();
        $total += $this->discoverFromAwwwards()->count();
        $total += $this->discoverFromCapterra()->count();
        $total += $this->discoverFromAlternativeTo()->count();
        $total += $this->discoverFromBuiltWith()->count();
        $total += $this->discoverFromSimilarWeb()->count();
        $total += $this->discoverFromStackShare()->count();

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
     * Discover popular software sites from broader G2 categories (not AI-specific).
     *
     * @return Collection<int, Site>
     */
    public function discoverFromG2Broad(): Collection
    {
        $urls = [];

        foreach (self::G2_BROAD_CATEGORY_URLS as $categoryUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($categoryUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractG2ProductUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch G2 broad page {$categoryUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'g2-broad');
    }

    /**
     * Discover popular sites from Downdetector trending/company listings.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromDowndetector(): Collection
    {
        $urls = [];

        foreach (self::DOWNDETECTOR_URLS as $ddUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($ddUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractDowndetectorUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch Downdetector page {$ddUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'downdetector');
    }

    /**
     * Discover top sites from the Tranco research ranking list.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromTrancoList(int $limit = 500): Collection
    {
        try {
            $response = Http::withHeaders($this->browserHeaders())
                ->timeout(30)
                ->get(self::TRANCO_LIST_URL);

            if (! $response->successful()) {
                Log::warning('SiteDiscovery: Failed to download Tranco list', ['status' => $response->status()]);

                return collect();
            }

            $urls = $this->extractTrancoUrls($response->body(), $limit);

            return $this->createSitesFromUrls($urls, 'tranco');
        } catch (\Throwable $e) {
            Log::warning('SiteDiscovery: Failed to fetch Tranco list', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * Discover award-winning sites from Awwwards.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromAwwwards(): Collection
    {
        $urls = [];

        foreach (self::AWWWARDS_URLS as $pageUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($pageUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractAwwwardsUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch Awwwards page {$pageUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'awwwards');
    }

    /**
     * Discover popular software from Capterra category pages.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromCapterra(): Collection
    {
        $urls = [];

        foreach (self::CAPTERRA_CATEGORY_URLS as $categoryUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($categoryUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractCapterraUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch Capterra page {$categoryUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'capterra');
    }

    /**
     * Discover popular software alternatives from AlternativeTo.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromAlternativeTo(): Collection
    {
        $urls = [];

        foreach (self::ALTERNATIVETO_URLS as $pageUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($pageUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractAlternativeToUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch AlternativeTo page {$pageUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'alternativeto');
    }

    /**
     * Discover top sites from BuiltWith technology pages.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromBuiltWith(): Collection
    {
        $urls = [];

        foreach (self::BUILTWITH_URLS as $pageUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($pageUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractBuiltWithUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch BuiltWith page {$pageUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'builtwith');
    }

    /**
     * Discover top websites from SimilarWeb rankings.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromSimilarWeb(): Collection
    {
        $urls = [];

        foreach (self::SIMILARWEB_URLS as $pageUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($pageUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractSimilarWebUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch SimilarWeb page {$pageUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'similarweb');
    }

    /**
     * Discover popular developer tools from StackShare.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromStackShare(): Collection
    {
        $urls = [];

        foreach (self::STACKSHARE_URLS as $pageUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($pageUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractStackShareUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch StackShare page {$pageUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'stackshare');
    }

    /**
     * Extract site URLs from Downdetector HTML.
     *
     * @return list<string>
     */
    private function extractDowndetectorUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // Downdetector links to company status pages: /status/SLUG/
        $links = $xpath->query('//a[contains(@href, "/status/")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if (preg_match('#/status/([a-z0-9-]+)#i', $href, $matches)) {
                    $slug = $matches[1];

                    // Convert slug to a likely base URL
                    $domain = str_replace('-', '', $slug).'.com';
                    $url = "https://{$domain}";

                    if ($this->isValidExternalUrl($url)) {
                        $urls[] = $url;
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * Extract domain URLs from a Tranco ZIP CSV response body.
     *
     * @return list<string>
     */
    private function extractTrancoUrls(string $zipContent, int $limit): array
    {
        $urls = [];

        // Write to temp file, unzip, read CSV
        $tmpZip = tempnam(sys_get_temp_dir(), 'tranco_');
        file_put_contents($tmpZip, $zipContent);

        $zip = new \ZipArchive;

        if ($zip->open($tmpZip) !== true) {
            unlink($tmpZip);

            return $urls;
        }

        $csvContent = $zip->getFromIndex(0);
        $zip->close();
        unlink($tmpZip);

        if (! $csvContent) {
            return $urls;
        }

        $lines = explode("\n", $csvContent);
        $count = 0;

        foreach ($lines as $line) {
            if ($count >= $limit) {
                break;
            }

            $parts = str_getcsv($line);

            if (count($parts) < 2) {
                continue;
            }

            $domain = trim($parts[1]);

            if (! $domain) {
                continue;
            }

            $url = "https://{$domain}";

            if ($this->isValidExternalUrl($url)) {
                $urls[] = $url;
                $count++;
            }
        }

        return $urls;
    }

    /**
     * Extract site URLs from Awwwards HTML.
     *
     * Awwwards showcases site entries with outbound links to the actual websites.
     *
     * @return list<string>
     */
    private function extractAwwwardsUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // Awwwards "Visit Site" links and outbound links to showcased websites
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
     * Extract software URLs from Capterra category HTML.
     *
     * Capterra lists products with links to their pages and external "visit website" links.
     *
     * @return list<string>
     */
    private function extractCapterraUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // Capterra product links and outbound visit-website links
        $links = $xpath->query('//a[contains(@href, "http") and (contains(@class, "visit") or contains(@data-action, "visit") or contains(@href, "goto"))]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        // Also extract product slugs from Capterra listing links
        $productLinks = $xpath->query('//a[contains(@href, "/software/") or contains(@href, "/p/")]');

        if ($productLinks) {
            foreach ($productLinks as $link) {
                $href = $link->getAttribute('href');

                if (preg_match('#/(?:software|p)/(\d+)/([a-z0-9-]+)#i', $href, $matches)) {
                    $slug = $matches[2];
                    $domain = str_replace('-', '', $slug).'.com';
                    $url = "https://{$domain}";

                    if ($this->isValidExternalUrl($url)) {
                        $urls[] = $url;
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * Extract site URLs from AlternativeTo HTML.
     *
     * AlternativeTo lists software with links to their official websites.
     *
     * @return list<string>
     */
    private function extractAlternativeToUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // AlternativeTo outbound links to software websites
        $links = $xpath->query('//a[contains(@class, "visit") or contains(@href, "out/")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        // Also grab any external http links from app listing cards
        $externalLinks = $xpath->query('//a[contains(@href, "http") and not(contains(@href, "alternativeto.net"))]');

        if ($externalLinks) {
            foreach ($externalLinks as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Extract site URLs from BuiltWith HTML.
     *
     * BuiltWith lists top sites using specific technologies with direct domain links.
     *
     * @return list<string>
     */
    private function extractBuiltWithUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // BuiltWith lists domains directly and links to detailed pages
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
     * Extract site URLs from SimilarWeb HTML.
     *
     * SimilarWeb top-sites rankings display domains with links.
     *
     * @return list<string>
     */
    private function extractSimilarWebUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // SimilarWeb shows domains in ranking tables/lists
        $links = $xpath->query('//a[contains(@href, "/website/")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                // Extract domain from SimilarWeb internal links like /website/example.com/
                if (preg_match('#/website/([a-z0-9.-]+\.[a-z]{2,})#i', $href, $matches)) {
                    $domain = $matches[1];
                    $url = "https://{$domain}";

                    if ($this->isValidExternalUrl($url)) {
                        $urls[] = $url;
                    }
                }
            }
        }

        // Also grab any direct external links
        $externalLinks = $xpath->query('//a[contains(@href, "http") and not(contains(@href, "similarweb.com"))]');

        if ($externalLinks) {
            foreach ($externalLinks as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Extract tool/site URLs from StackShare HTML.
     *
     * StackShare lists developer tools with links to their official websites.
     *
     * @return list<string>
     */
    private function extractStackShareUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // StackShare external links to tool websites
        $links = $xpath->query('//a[contains(@href, "http") and not(contains(@href, "stackshare.io"))]');

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

            if (app(DomainFilterService::class)->isBlocked($domain)) {
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

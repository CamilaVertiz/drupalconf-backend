<?php

declare(strict_types=1);

namespace Drupal\drupalconf_default_content\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\State\StateInterface;
use Drupal\drupalconf_reservations\ReservationStatus;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Seeds paragraph-based pages after the DrupalConf recipe is applied.
 */
final class RecipePagesSubscriber implements EventSubscriberInterface {

  /**
   * The name of the recipe this subscriber responds to.
   */
  protected const RECIPE_NAME = 'DrupalConf';

  /**
   * State key guarding against re-seeding.
   */
  protected const STATE_KEY = 'drupalconf_default_content.seeded';

  public function __construct(
    private readonly StateInterface $state,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RecipeAppliedEvent::class => 'onRecipeApplied'];
  }

  /**
   * Seeds the default content when the DrupalConf recipe is applied.
   *
   * @param \Drupal\Core\Recipe\RecipeAppliedEvent $event
   *   The recipe applied event.
   */
  public function onRecipeApplied(RecipeAppliedEvent $event): void {
    if ($event->recipe->name !== self::RECIPE_NAME) {
      return;
    }
    if ($this->state->get(self::STATE_KEY)) {
      return;
    }
    $home = $this->seedBasicPages();
    $this->setFrontPage($home);
    $this->seedMainMenu();
    $this->seedBlogs();
    $this->seedReservations();
    $this->state->set(self::STATE_KEY, TRUE);
  }

  /**
   * Seeds the basic pages.
   *
   * @return \Drupal\node\NodeInterface
   *   The Home node.
   */
  protected function seedBasicPages(): NodeInterface {
    $home = $this->createBasicPage('Home', '/home', [
      $this->createHero(
        'Welcome to DrupalConf',
        'The annual gathering',
        'Three days of talks, workshops and code sprints with the global Drupal community.',
        ['uri' => 'internal:/conferences', 'title' => 'See conferences']
      ),
      $this->createCopy(
        'Why DrupalConf',
        '<p>Meet maintainers, agencies, and contributors shaping the future of Drupal. Learn from real-world case studies and contribute back during the sprints.</p>'
      ),
      $this->createListing('Upcoming conferences', 'conference'),
      $this->createListing('Our sponsors', 'sponsor'),
    ]);

    $this->createBasicPage('About', '/about', [
      $this->createHero(
        'About DrupalConf',
        'Our story',
        'DrupalConf is a community-driven event organized by volunteers and supported by agencies and sponsors from around the world.',
        ['uri' => 'internal:/contact', 'title' => 'Get in touch']
      ),
      $this->createCopy(
        'Our mission',
        '<p>We exist to bring the Drupal community together, share knowledge, and grow the project. The conference is run as a non-profit initiative and reinvests every dollar back into community programs.</p>'
      ),
    ]);

    $this->createBasicPage('Contact', '/contact', [
      $this->createHero(
        'Contact us',
        'Got a question?',
        'Reach out for sponsorship enquiries, speaker proposals, or general questions about the conference.',
        NULL
      ),
      $this->createContactForm(
        'Send us a message',
        '<p>Fill in the form below and a member of the organizing team will get back to you within two business days.</p>'
      ),
    ]);

    $this->createBasicPage('Conferences', '/conferences', [
      $this->createHero(
        'Conferences',
        'Past, present and future',
        'Browse upcoming editions and revisit recordings and resources from previous gatherings.',
        NULL
      ),
      $this->createListing('All conferences', 'conference'),
    ]);

    $this->createBasicPage('Blog', '/blogs', [
      $this->createHero(
        'Blog',
        'News and stories',
        'Announcements, recaps, and stories from the DrupalConf community.',
        NULL
      ),
      $this->createListing('Latest posts', 'blog'),
    ]);

    return $home;
  }

  /**
   * Seeds the blog posts.
   */
  protected function seedBlogs(): void {
    $blogs = [
      [
        'title' => 'Drupal 12 Targets August 2026 Release',
        'eyebrow' => 'Release',
        'tagline' => 'Core team focuses on release-critical work.',
        'link' => [
          'uri' => 'https://www.thedroptimes.com/67207/drupal-12-targets-august-2026-core-team-focuses-release-critical-work',
          'title' => 'Read on TheDropTimes',
        ],
        'copy' => '<p>Drupal is now targeting the week of <strong>10 August 2026</strong> for its 12.0.0 release, after core maintainers confirmed that critical requirements will not be completed in time for the earlier June window.</p><p>Drupal 12 has moved into its second release window. The revised timeline shifts attention to a smaller set of high-priority tasks spanning testing infrastructure, frontend tooling, upgrade paths, and core cleanup.</p>',
      ],
      [
        'title' => 'PHPUnit 12 Support Lands in Drupal Core',
        'eyebrow' => 'Engineering',
        'tagline' => 'Aligning the testing framework with the latest PHPUnit.',
        'link' => ['uri' => 'https://www.drupal.org/news', 'title' => 'Drupal news'],
        'copy' => '<p>One of Drupal 12\'s release-critical tasks is adding support for <strong>PHPUnit 12</strong>, which introduces API changes that Drupal must adopt before progressing further with its testing framework.</p><p>The work updates a large number of base test classes and trait helpers across core, and unblocks contributed modules that have been waiting on the new PHPUnit baseline.</p>',
      ],
      [
        'title' => 'JavaScript Import Maps API Comes to Drupal',
        'eyebrow' => 'Frontend',
        'tagline' => 'A new core API to align with CKEditor 5\'s installation model.',
        'link' => ['uri' => 'https://www.drupal.org/news', 'title' => 'Drupal news'],
        'copy' => '<p>Drupal core is introducing a <strong>JavaScript import maps API</strong>, tracked in the import maps initiative, to align with changes in CKEditor 5\'s installation model and ensure compatibility with future editor integrations.</p><p>The new API gives module authors a standard way to declare and resolve frontend dependencies without bundling, paving the way for a more modern asset pipeline.</p>',
      ],
      [
        'title' => 'Drupal 10 Support Extended to December 2026',
        'eyebrow' => 'Security',
        'tagline' => 'Deprecations deferred to Drupal 13.',
        'link' => [
          'uri' => 'https://www.thedroptimes.com/50305/drupal-10-support-extended-december-2026-deprecations-deferred-drupal-13',
          'title' => 'Read on TheDropTimes',
        ],
        'copy' => '<p><strong>Drupal 10.6.x</strong> will receive security support until <strong>December 2026</strong>, while Drupal 10.5.x will continue to receive security support until June 2026.</p><p>Recent security releases fixed critical cross-site scripting vulnerabilities and a moderately critical gadget chain vulnerability. Site owners on Drupal 10 now have a longer runway to plan their upgrade to Drupal 12.</p>',
      ],
      [
        'title' => 'Bálint Kléri Named Drupal Frontend Lead',
        'eyebrow' => 'Community',
        'tagline' => 'A new leadership role for Drupal CMS, Mercury and Mercury-based themes.',
        'link' => ['uri' => 'https://www.drupal.org/news', 'title' => 'Drupal news'],
        'copy' => '<p><strong>Bálint Kléri</strong> has been named Frontend Lead, a new leadership role created to oversee the frontend architecture for <strong>Drupal CMS, Mercury and Mercury-based themes</strong>.</p><p>The position consolidates frontend decision-making and helps coordinate the work of theme maintainers, the import maps initiative, and the broader CKEditor and admin-UI tracks.</p>',
      ],
      [
        'title' => 'Drupal AI Initiative Marks Its First Year',
        'eyebrow' => 'AI',
        'tagline' => '34 partners, $380K in cash, and $1.5M in in-kind contributions.',
        'link' => ['uri' => 'https://www.drupal.org/news', 'title' => 'Drupal news'],
        'copy' => '<p>The <strong>Drupal AI Initiative</strong> marks its first year since the spark of conception at Drupal Developer Days in Leuven, having grown into the largest multi-company collaboration in Drupal community history.</p><p>Launched officially in June 2025, the initiative has expanded to <strong>34 partners</strong>, reached nearly <strong>14,000 installs</strong> growing at approximately 260 sites per week, and secured <strong>$380,000 in cash</strong> alongside <strong>$1.5 million in in-kind contributions</strong>.</p>',
      ],
    ];

    foreach ($blogs as $blog) {
      $hero = $this->createHero(
        $blog['title'],
        $blog['eyebrow'],
        $blog['tagline'],
        $blog['link']
      );
      $copy = $this->createCopy($blog['title'], $blog['copy']);

      $this->createNode([
        'type' => 'blog',
        'title' => $blog['title'],
        'field_content' => [
          ['target_id' => $hero->id(), 'target_revision_id' => $hero->getRevisionId()],
          ['target_id' => $copy->id(), 'target_revision_id' => $copy->getRevisionId()],
        ],
      ]);
    }
  }

  /**
   * Sets the site's default front page to the Home node.
   *
   * @param \Drupal\node\NodeInterface $home
   *   The Home node.
   */
  protected function setFrontPage(NodeInterface $home): void {
    $this->configFactory->getEditable('system.site')
      ->set('page.front', '/node/' . $home->id())
      ->save();
  }

  /**
   * Seeds a demo reservation for each session.
   */
  protected function seedReservations(): void {
    $sessions = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => 'session']);
    $storage = $this->entityTypeManager->getStorage('reservation');
    foreach ($sessions as $session) {
      $storage->create([
        'session' => $session->id(),
        'email' => 'demo@drupalconf.example',
        'name' => 'Demo Attendee',
        'status' => ReservationStatus::Reserved->value,
      ])->save();
    }
  }

  /**
   * Seeds the main menu links.
   */
  protected function seedMainMenu(): void {
    $aliases = [
      'Home' => '/home',
      'Conferences' => '/conferences',
      'Blog' => '/blogs',
      'About' => '/about',
      'Contact' => '/contact',
    ];

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $weight = 0;
    foreach ($aliases as $title => $alias) {
      $link = $storage->create([
        'title' => $title,
        'link' => ['uri' => 'internal:' . $alias],
        'menu_name' => 'main',
        'weight' => $weight++,
        'expanded' => TRUE,
      ]);
      $link->save();
    }
  }

  /**
   * Creates a basic page node.
   *
   * @param string $title
   *   The page title.
   * @param string $alias
   *   The path alias.
   * @param array<int, \Drupal\paragraphs\ParagraphInterface> $paragraphs
   *   The page paragraphs.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createBasicPage(string $title, string $alias, array $paragraphs): NodeInterface {
    $values = [
      'type' => 'basic_page',
      'title' => $title,
      'path' => ['alias' => $alias, 'pathauto' => 0],
      'field_content' => array_map(static fn (ParagraphInterface $p) => [
        'target_id' => $p->id(),
        'target_revision_id' => $p->getRevisionId(),
      ], $paragraphs),
    ];
    return $this->createNode($values);
  }

  /**
   * Creates a hero paragraph.
   *
   * @param string $title
   *   The hero title.
   * @param string|null $eyebrow
   *   The eyebrow label, or NULL.
   * @param string $copy
   *   The hero body copy.
   * @param array<string, mixed>|null $link
   *   The link, or NULL.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  protected function createHero(string $title, ?string $eyebrow, string $copy, ?array $link): ParagraphInterface {
    return $this->createParagraph('hero', [
      'field_title' => $title,
      'field_eyebrow' => $eyebrow,
      'field_copy' => ['value' => '<p>' . $copy . '</p>', 'format' => 'basic_html'],
      'field_link' => $link,
    ]);
  }

  /**
   * Creates a copy paragraph.
   *
   * @param string $title
   *   The paragraph title.
   * @param string $html
   *   The body HTML.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  protected function createCopy(string $title, string $html): ParagraphInterface {
    return $this->createParagraph('copy', [
      'field_title' => $title,
      'field_copy' => ['value' => $html, 'format' => 'basic_html'],
    ]);
  }

  /**
   * Creates a listing paragraph.
   *
   * @param string $title
   *   The listing title.
   * @param string $bundle
   *   The content bundle to list.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  protected function createListing(string $title, string $bundle): ParagraphInterface {
    return $this->createParagraph('listing', [
      'field_title' => $title,
      'field_bundle' => $bundle,
    ]);
  }

  /**
   * Creates a contact_form paragraph.
   *
   * @param string $title
   *   The paragraph title.
   * @param string $intro
   *   The intro HTML.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  protected function createContactForm(string $title, string $intro): ParagraphInterface {
    return $this->createParagraph('contact_form', [
      'field_title' => $title,
      'field_copy' => ['value' => $intro, 'format' => 'basic_html'],
    ]);
  }

  /**
   * Creates and saves a paragraph of the given type.
   *
   * @param string $type
   *   The paragraph bundle.
   * @param array<string, mixed> $values
   *   The field values.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  protected function createParagraph(string $type, array $values): ParagraphInterface {
    $values['type'] = $type;
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create($values);
    assert($paragraph instanceof ParagraphInterface);
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Creates and saves a node.
   *
   * @param array<string, mixed> $values
   *   The field values.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createNode(array $values): NodeInterface {
    $values += ['uid' => 1, 'status' => 1];
    $node = $this->entityTypeManager->getStorage('node')->create($values);
    $node->save();
    return $node;
  }

}

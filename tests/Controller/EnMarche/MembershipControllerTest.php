<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadHomeBlockData;
use AppBundle\Donation\DonationRequest;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\AdherentActivationToken;
use AppBundle\Geocoder\Coordinates;
use AppBundle\Mailer\Message\AdherentAccountActivationMessage;
use AppBundle\Mailer\Message\AdherentAccountConfirmationMessage;
use AppBundle\Repository\AdherentActivationTokenRepository;
use AppBundle\Repository\AdherentRepository;
use AppBundle\Repository\EmailRepository;
use AppBundle\Membership\MembershipUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\AppBundle\Controller\ControllerTestTrait;
use Tests\AppBundle\MysqlWebTestCase;

/**
 * @group functional
 * @group membership
 */
class MembershipControllerTest extends MysqlWebTestCase
{
    use ControllerTestTrait;

    /**
     * @var AdherentRepository
     */
    private $adherentRepository;

    /**
     * @var AdherentActivationTokenRepository
     */
    private $activationTokenRepository;

    /**
     * @var EmailRepository
     */
    private $emailRepository;

    public function testCreateMembershipAccountForFrenchAdherentIsSuccessful()
    {
        $this->authenticateAsAdherent($this->client, 'foo.bar@example.ch');
        $crawler = $this->client->request(Request::METHOD_GET, '/adhesion');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $this->client->submit($crawler->selectButton('J\'adhère')->form(), static::createFormData());

        $this->assertClientIsRedirectedTo('/espace-adherent/accueil', $this->client);

        $this->client->followRedirect();

        $adherent = $this->getAdherentRepository()->findOneByEmail('foo.bar@example.ch');
        $this->assertInstanceOf(Adherent::class, $adherent);
        $this->assertSame('male', $adherent->getGender());
        $this->assertSame('Foo', $adherent->getFirstName());
        $this->assertSame('Bar', $adherent->getLastName());
        $this->assertSame('92 Bld Victor Hugo', $adherent->getAddress());
        $this->assertSame('Clichy', $adherent->getCityName());
        $this->assertSame('FR', $adherent->getCountry());
        $this->assertSame('20-01-1950', $adherent->getBirthdate()->format('d-m-Y'));
        $this->assertTrue($adherent->getComMobile());
        $this->assertFalse($adherent->getComEmail());
        $this->assertNotNull($adherent->getLatitude());
        $this->assertNotNull($adherent->getLongitude());

        $this->assertInstanceOf(
            Adherent::class,
            $adherent = $this->client->getContainer()->get('doctrine')->getRepository(Adherent::class)->findOneByEmail('foo.bar@example.ch')
        );

        $this->assertInstanceOf(AdherentActivationToken::class, $activationToken = $this->activationTokenRepository->findAdherentMostRecentKey((string) $adherent->getUuid()));
        $this->assertCount(1, $this->emailRepository->findRecipientMessages(AdherentAccountActivationMessage::class, 'foo.bar@example.ch'));

        $session = $this->client->getRequest()->getSession();

        $this->assertInstanceOf(DonationRequest::class, $session->get(MembershipUtils::REGISTERING_DONATION));
        $this->assertSame($adherent->getId(), $session->get(MembershipUtils::NEW_ADHERENT_ID));

        // Activate the user account
        $activateAccountUrl = sprintf('/inscription/finaliser/%s/%s', $adherent->getUuid(), $activationToken->getValue());
        $this->client->request(Request::METHOD_GET, $activateAccountUrl);

        $this->assertResponseStatusCode(Response::HTTP_FOUND, $this->client->getResponse());
        $this->assertCount(1, $this->emailRepository->findRecipientMessages(AdherentAccountConfirmationMessage::class, 'foo.bar@example.ch'));
        $this->assertClientIsRedirectedTo('/evenements', $this->client);

        $crawler = $this->client->followRedirect();

        // User is automatically logged-in and redirected to the events page
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertContains('Votre compte adhérent est maintenant actif.', $crawler->filter('#notice-flashes')->text());
    }

    /**
     * @dataProvider provideSuccessfulMembershipRequests
     */
    public function testCreateMembershipAccountIsSuccessful($country, $city, $cityName, $postalCode, $address)
    {
        $this->authenticateAsAdherent($this->client, 'foo.bar@example.ch');
        $this->client->request(Request::METHOD_GET, '/adhesion');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $data = static::createFormData();
        $data['membership_request']['address']['country'] = $country;
        $data['membership_request']['address']['city'] = $city;
        $data['membership_request']['address']['cityName'] = $cityName;
        $data['membership_request']['address']['postalCode'] = $postalCode;
        $data['membership_request']['address']['address'] = $address;

        $this->client->submit($this->client->getCrawler()->selectButton('J\'adhère')->form(), $data);

        $this->assertClientIsRedirectedTo('/espace-adherent/accueil', $this->client);

        $adherent = $this->getAdherentRepository()->findOneByEmail('foo.bar@example.ch');
        $this->assertInstanceOf(Adherent::class, $adherent);
        $this->assertNotNull($adherent->getLatitude());
        $this->assertNotNull($adherent->getLongitude());

        $session = $this->client->getRequest()->getSession();

        $this->assertInstanceOf(DonationRequest::class, $donation = $session->get(MembershipUtils::REGISTERING_DONATION));
        $this->assertSame($adherent->getId(), $session->get(MembershipUtils::NEW_ADHERENT_ID));
        $this->assertSame('Bar', $donation->getLastName());
    }

    public function provideSuccessfulMembershipRequests()
    {
        return [
            'Foreign' => ['CH', '', 'Zürich', '8057', '36 Zeppelinstrasse'],
            'DOM-TOM Réunion' => ['FR', '97437-97410', '', '97437', '20 Rue Francois Vitry'],
            'DOM-TOM Guadeloupe' => ['FR', '97110-97120', '', '97110', '18 Rue Roby Petreluzzi'],
            'DOM-TOM Polynésie' => ['FR', '98714-98735', '', '98714', '45 Avenue du Maréchal Foch'],
        ];
    }

    public function testLoginAfterCreatingMembershipAccountWithoutConfirmItsEmail()
    {
        // register
        $this->authenticateAsAdherent($this->client, 'foo.bar@example.ch');
        $this->client->request(Request::METHOD_GET, '/adhesion');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $data = static::createFormData();
        $data['membership_request']['address']['country'] = 'CH';
        $data['membership_request']['address']['city'] = '';
        $data['membership_request']['address']['cityName'] = 'Zürich';
        $data['membership_request']['address']['postalCode'] = '8057';
        $data['membership_request']['address']['address'] = '36 Zeppelinstrasse';

        $this->client->submit($this->client->getCrawler()->selectButton('J\'adhère')->form(), $data);

        $this->assertClientIsRedirectedTo('/espace-adherent/accueil', $this->client);
    }

    public function testDonateWithoutTemporaryDonation()
    {
        $client = $this->client;
        $client->request(Request::METHOD_GET, '/inscription/don');

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $client->getResponse());
    }

    public function testDonateWithAFakeValue()
    {
        // register
        $this->authenticateAsAdherent($this->client, 'foo.bar@example.ch');
        $this->client->request(Request::METHOD_GET, '/adhesion');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $data = static::createFormData();
        $data['membership_request']['address']['country'] = 'CH';
        $data['membership_request']['address']['city'] = '';
        $data['membership_request']['address']['cityName'] = 'Zürich';
        $data['membership_request']['address']['postalCode'] = '8057';
        $data['membership_request']['address']['address'] = '36 Zeppelinstrasse';

        $this->client->submit($this->client->getCrawler()->selectButton('J\'adhère')->form(), $data);

        $this->assertClientIsRedirectedTo('/espace-adherent/accueil', $this->client);

        $this->client->request(Request::METHOD_GET, '/inscription/don');
        $crawler = $this->client->getCrawler();
        $form = $crawler->selectButton('Je soutiens maintenant')->form();
        $this->client->submit($form, ['app_donation[amount]' => 'NaN']);

        $this->assertNotSame(500, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider provideRegistrationOnBoardingStepUrl
     */
    public function testRegistrationOnBoardingWithoutNewAdherentId(string $stepUrl)
    {
        $this->client->request(Request::METHOD_GET, '/inscription/'.$stepUrl);

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    /**
     * @dataProvider provideRegistrationOnBoardingStepUrl
     */
    public function testRegistrationOnBoardingWithWrongNewAdherentId(string $stepUrl)
    {
        // Set a wrong id
        $this->client->getContainer()->get('session')->set(MembershipUtils::NEW_ADHERENT_ID, 1234);

        $this->client->request(Request::METHOD_GET, '/inscription/'.$stepUrl);

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    public function provideRegistrationOnBoardingStepUrl()
    {
        yield ['centre-interets'];

        yield ['choisir-des-comites'];
    }

    public function testPinInterests()
    {
        $adherent = $this->getAdherentRepository()->findOneByEmail('michelle.dufour@example.ch');

        $this->client->getContainer()->get('session')->set(MembershipUtils::NEW_ADHERENT_ID, $adherent->getId());

        $crawler = $this->client->request(Request::METHOD_GET, '/inscription/centre-interets');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $checkBoxPattern = '#app_adherent_pin_interests '.
                           'input[type="checkbox"][name="app_adherent_pin_interests[interests][]"]';

        $this->assertCount(18, $checkboxes = $crawler->filter($checkBoxPattern));

        $interests = $this->client->getContainer()->getParameter('adherent_interests');
        $interestsValues = array_keys($interests);
        $interestsLabels = array_values($interests);

        foreach ($checkboxes as $i => $checkbox) {
            $this->assertSame($interestsValues[$i], $checkbox->getAttribute('value'));
            $this->assertSame($interestsLabels[$i], $crawler->filter('label[for="app_adherent_pin_interests_interests_'.$i.'"]')->eq(0)->text());
        }
    }

    public function testPinInterestsPersistsInterestsForNonActivatedAdherent()
    {
        /** @var Adherent $adherent */
        $adherent = $this->getAdherentRepository()->findOneByEmail('michelle.dufour@example.ch');

        $this->assertFalse($adherent->isEnabled());
        $this->assertEmpty($adherent->getInterests());

        $this->client->getContainer()->get('session')->set(MembershipUtils::NEW_ADHERENT_ID, $adherent->getId());

        $crawler = $this->client->request(Request::METHOD_GET, '/inscription/centre-interets');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $interests = $this->client->getContainer()->getParameter('adherent_interests');
        $interestsValues = array_keys($interests);

        $chosenInterests = [
            4 => $interestsValues[4],
            8 => $interestsValues[8],
        ];

        $this->client->submit($crawler->selectButton('app_adherent_pin_interests[submit]')->form(), [
            'app_adherent_pin_interests' => [
                'interests' => $chosenInterests,
            ],
        ]);

        $this->assertClientIsRedirectedTo('/inscription/choisir-des-comites', $this->client);

        $this->manager->clear();

        /** @var Adherent $adherent */
        $adherent = $this->getAdherentRepository()->findOneByEmail('michelle.dufour@example.ch');

        $this->assertSame(array_values($chosenInterests), $adherent->getInterests());
    }

    public function testChooseNearbyCommittee()
    {
        $adherent = $this->getAdherentRepository()->findOneByEmail('michelle.dufour@example.ch');
        $coordinates = new Coordinates($adherent->getLatitude(), $adherent->getLongitude());

        $this->client->getContainer()->get('session')->set(MembershipUtils::NEW_ADHERENT_ID, $adherent->getId());

        $crawler = $this->client->request(Request::METHOD_GET, '/inscription/choisir-des-comites');

        $boxPattern = '#app_membership_choose_nearby_committee_committees > div';

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertCount(3, $boxes = $crawler->filter($boxPattern));

        $committees = $this->getCommitteeRepository()->findNearbyCommittees(3, $coordinates);

        foreach ($boxes as $i => $box) {
            $checkbox = $crawler->filter($boxPattern.' input[type="checkbox"][name="app_membership_choose_nearby_committee[committees][]"]');

            $this->assertSame((string) $committees[$i]->getUuid(), $checkbox->eq($i)->attr('value'));
            $this->assertSame($committees[$i]->getName(), $crawler->filter($boxPattern.' h3')->eq($i)->text());
        }
    }

    public function testChooseNearbyCommitteePersistsMembershipForNonActivatedAdherent()
    {
        $adherent = $this->getAdherentRepository()->findOneByEmail('michelle.dufour@example.ch');
        $memberships = $this->getCommitteeMembershipRepository()->findMemberships($adherent);
        $coordinates = new Coordinates($adherent->getLatitude(), $adherent->getLongitude());

        $this->assertFalse($adherent->isEnabled());
        $this->assertCount(0, $memberships);

        $this->client->getContainer()->get('session')->set(MembershipUtils::NEW_ADHERENT_ID, $adherent->getId());

        $crawler = $this->client->request(Request::METHOD_GET, '/inscription/choisir-des-comites');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $committees = $this->getCommitteeRepository()->findNearbyCommittees(3, $coordinates);

        $this->assertCount(3, $committees, 'New adherent should have 3 committee proposals');

        // We are 'checking' the first (0) and the last one (2)
        $this->client->submit($crawler->selectButton('app_membership_choose_nearby_committee[submit]')->form(), [
            'app_membership_choose_nearby_committee' => [
                'committees' => [
                    0 => $committees[0]->getUuid(),
                    2 => $committees[2]->getUuid(),
                ],
            ],
        ]);

        $this->assertClientIsRedirectedTo('/inscription/terminee', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertContains('Finalisez dès maintenant votre adhésion', $crawler->text());

        $memberships = $this->getCommitteeMembershipRepository()->findMemberships($adherent);

        $this->assertCount(2, $memberships);
    }

    private static function createFormData()
    {
        return [
            'membership_request' => [
                'gender' => 'male',
                'address' => [
                    'country' => 'FR',
                    'city' => '92110-92024',
                    'cityName' => '',
                    'address' => '92 Bld Victor Hugo',
                ],
                'phone' => [
                    'country' => 'FR',
                    'number' => '0140998080',
                ],
                'birthdate' => [
                    'year' => '1950',
                    'month' => '1',
                    'day' => '20',
                ],
                'comMobile' => true,
            ],
        ];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadAdherentData::class,
            LoadHomeBlockData::class,
        ]);

        $this->adherentRepository = $this->getAdherentRepository();
        $this->activationTokenRepository = $this->getActivationTokenRepository();
        $this->emailRepository = $this->getEmailRepository();
    }

    protected function tearDown()
    {
        $this->kill();

        $this->emailRepository = null;
        $this->activationTokenRepository = null;
        $this->adherentRepository = null;

        parent::tearDown();
    }
}

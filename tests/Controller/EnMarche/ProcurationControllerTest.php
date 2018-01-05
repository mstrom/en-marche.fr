<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadElectionData;
use AppBundle\DataFixtures\ORM\LoadHomeBlockData;
use AppBundle\DataFixtures\ORM\LoadProcurationData;
use AppBundle\Entity\Election;
use AppBundle\Entity\ElectionRound;
use AppBundle\Entity\ProcurationProxy;
use AppBundle\Entity\ProcurationRequest;
use AppBundle\Procuration\ElectionContext;
use AppBundle\Procuration\ProcurationSession;
use AppBundle\Repository\ProcurationProxyRepository;
use AppBundle\Repository\ProcurationRequestRepository;
use libphonenumber\PhoneNumber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Client;
use Tests\AppBundle\Controller\ControllerTestTrait;
use Tests\AppBundle\SqliteWebTestCase;

/**
 * @group functional
 */
class ProcurationControllerTest extends SqliteWebTestCase
{
    use ControllerTestTrait;

    /** @var Client */
    private $client;

    /** @var ProcurationRequestRepository */
    private $procurationRequestRepostitory;

    /** @var ProcurationProxyRepository */
    private $procurationProxyRepostitory;

    public function testLandingWithoutComingElection()
    {
        $this->loadFixtures([]); // We need empty tables for this test only

        $crawler = $this->client->request(Request::METHOD_GET, '/procuration');

        $this->isSuccessful($this->client->getResponse());
        $this->assertSame(
            'No coming election, TODO.',
            trim($crawler->filter('.procuration__header--inner')->text())
        );
        $this->assertSame(
            'No coming election, TODO.',
            trim($crawler->filter('.procuration__content')->text())
        );
    }

    public function testLanding()
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/procuration');

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSame(
            'Chaque vote compte.',
            trim($crawler->filter('.procuration__header--inner > h1')->text())
        );
        $this->assertSame(
            'L\'élection législative partielle pour la 1ère circonscription du Val-d\'Oise aura lieu les 28 janvier et 4 février 2018.',
            trim($crawler->filter('.procuration__header--inner > h2')->text())
        );
        $this->assertSame(
            'Si vous ne votez pas en France métropolitaine, renseignez-vous sur les dates.',
            trim($crawler->filter('.procuration__header--inner > div.text--body')->text())
        );
        $this->assertCount(1, $crawler->filter('.procuration__content a:contains("Je me porte mandataire")'));
    }

    public function testChooseElectionOnRequest()
    {
        $this->assertFalse(
            $this->container->get(ProcurationSession::class)->hasElectionContext(),
            'The session should not have an election context yet.'
        );

        $crawler = $this->client->request(Request::METHOD_GET, '/procuration/choisir/'.ElectionContext::ACTION_REQUEST);

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSame(
            'Un de nos volontaires peut porter votre voix',
            $crawler->filter('h2')->text()
        );
        $this->assertCount(1, $crawler->filter('#election_context_elections input[type="checkbox"]'));
        $this->assertSame(
            'Élection législative partielle pour la 1ère circonscription du Val-d\'Oise',
            $crawler->filter('#election_context_elections label')->text()
        );

        $crawler = $this->client->submit($crawler->selectButton('Continuer')->form());

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertCount(1, $error = $crawler->filter('.form__error'));
        $this->assertSame('Vous devez choisir au moins une élection.', $error->text());

        $this->client->submit($crawler->selectButton('Continuer')->form(['election_context[elections]' => [3]]));

        $this->assertClientIsRedirectedTo('/procuration/je-demande/'.ProcurationRequest::STEP_URI_VOTE, $this->client);
        $this->assertTrue(
            $this->client->getContainer()->get(ProcurationSession::class)->hasElectionContext(),
            'The session should have saved an election context.'
        );
    }

    public function testChooseElectionOnProposal()
    {
        $this->assertFalse(
            $this->container->get(ProcurationSession::class)->hasElectionContext(),
            'The session should not have an election context yet.'
        );

        $crawler = $this->client->request(Request::METHOD_GET, '/procuration/choisir/'.ElectionContext::ACTION_PROPOSAL);

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSame(
            'Proposez-vous en tant que mandataire, à qui un citoyen de votre ville peut donner procuration.',
            $crawler->filter('h2')->text()
        );
        $this->assertCount(1, $crawler->filter('#election_context_elections input[type="checkbox"]'));
        $this->assertSame(
            'Élection législative partielle pour la 1ère circonscription du Val-d\'Oise',
            $crawler->filter('#election_context_elections label')->text()
        );

        $crawler = $this->client->submit($crawler->selectButton('Continuer')->form());

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertCount(1, $error = $crawler->filter('.form__error'));
        $this->assertSame('Vous devez choisir au moins une élection.', $error->text());

        $this->client->submit($crawler->selectButton('Continuer')->form(['election_context[elections]' => [3]]));

        $this->assertClientIsRedirectedTo('/procuration/je-propose', $this->client);
        $this->assertTrue(
            $this->client->getContainer()->get(ProcurationSession::class)->hasElectionContext(),
            'The session should have saved an election context.'
        );
    }

    public function testProcurationRequestLegacyIndex()
    {
        $this->client->request(Request::METHOD_GET, '/procuration/je-demande');

        $this->assertClientIsRedirectedTo('/procuration/je-demande/mon-lieu-de-vote', $this->client, false, true);
    }

    /**
     * @dataProvider provideStepsRequiringElectionContext
     */
    public function testProcurationRequestNeedsElectionContext(string $step)
    {
        $this->client->request(Request::METHOD_GET, "/procuration/je-demande/$step");

        $this->assertClientIsRedirectedTo('/procuration/choisir/'.ElectionContext::ACTION_REQUEST, $this->client);
    }

    public function provideStepsRequiringElectionContext(): iterable
    {
        yield [ProcurationRequest::STEP_URI_VOTE];
        yield [ProcurationRequest::STEP_URI_PROFILE];
        yield [ProcurationRequest::STEP_URI_ELECTION_ROUNDS];
    }

    public function testProcurationRequest()
    {
        $this->setElectionContext();

        $procurationRequest = new ProcurationRequest();

        $this->assertCurrentProcurationRequestSameAs($procurationRequest);
        $this->assertCount(5, $this->procurationRequestRepostitory->findAll());

        // Initial form
        $crawler = $this->client->request(Request::METHOD_GET, '/procuration/je-demande/'.ProcurationRequest::STEP_URI_VOTE);

        $this->isSuccessful($this->client->getResponse());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'app_procuration_request' => [
                'voteCountry' => 'FR',
                'votePostalCode' => '92110',
                'voteCity' => '92110-92024',
                'voteCityName' => '',
                'voteOffice' => 'TestOfficeName',
            ],
        ]));

        $this->assertClientIsRedirectedTo('/procuration/je-demande/'.ProcurationRequest::STEP_URI_PROFILE, $this->client);

        $procurationRequest->setVoteCountry('FR');
        $procurationRequest->setVotePostalCode('92110');
        $procurationRequest->setVoteCity('92110-92024');
        $procurationRequest->setVoteCityName('');
        $procurationRequest->setVoteOffice('TestOfficeName');

        $this->assertCurrentProcurationRequestSameAs($procurationRequest);

        // Profile
        $crawler = $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        $crawler = $this->client->submit($crawler->selectButton('Je continue')->form([
            'app_procuration_request' => [
                'gender' => 'male',
                'firstNames' => 'Paul, Jean, Martin',
                'lastName' => 'Dupont',
                'emailAddress' => 'timothe.baume@example.gb',
                'address' => '6 rue Neyret',
                'country' => 'FR',
                'postalCode' => '69001',
                'city' => '69001-69381',
                'cityName' => '',
                'phone' => [
                    'country' => 'FR',
                    'number' => '',
                ],
                'birthdate' => [
                    'year' => '1950',
                    'month' => '1',
                    'day' => '20',
                ],
            ],
        ]));

        $this->isSuccessful($this->client->getResponse());
        $this->assertSame('Le numéro de téléphone est obligatoire.', $crawler->filter('.form__error')->text());
        $this->assertSame(0, $crawler->filter('.form--warning')->count());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'app_procuration_request' => [
                'gender' => 'male',
                'firstNames' => 'Paul, Jean, Martin',
                'lastName' => 'Dupont',
                'emailAddress' => 'timothe.baume@example.gb',
                'address' => '6 rue Neyret',
                'country' => 'FR',
                'postalCode' => '69001',
                'city' => '69001-69381',
                'cityName' => '',
                'phone' => [
                    'country' => 'FR',
                    'number' => '0140998080',
                ],
                'birthdate' => [
                    'year' => '1950',
                    'month' => '1',
                    'day' => '20',
                ],
            ],
        ]));

        $this->assertClientIsRedirectedTo('/procuration/je-demande/'.ProcurationRequest::STEP_URI_ELECTION_ROUNDS, $this->client);

        $procurationRequest->setGender('male');
        $procurationRequest->setFirstNames('Paul, Jean, Martin');
        $procurationRequest->setLastName('Dupont');
        $procurationRequest->setEmailAddress('timothe.baume@example.gb');
        $procurationRequest->setAddress('6 rue Neyret');
        $procurationRequest->setCountry('FR');
        $procurationRequest->setPostalCode('69001');
        $procurationRequest->setCity('69001-69381');
        $procurationRequest->setCityName('');
        $procurationRequest->setPhone($this->createPhoneNumber('33', '140998080'));
        $procurationRequest->setBirthdate(date_create_from_format('Y m d His', '1950 1 20 000000'));

        $this->assertCurrentProcurationRequestSameAs($procurationRequest);

        // Elections
        $crawler = $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        $crawler = $this->client->submit($crawler->selectButton('Je continue')->form([
            'g-recaptcha-response' => 'dummy',
            'app_procuration_request' => [
                'electionRounds' => [],
                'reason' => ProcurationRequest::REASON_HEALTH,
                'authorization' => true,
            ],
        ]));

        $this->isSuccessful($this->client->getResponse());
        $this->assertSame('Vous devez choisir au moins un tour d\'élection.', $crawler->filter('.form__error')->text());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'g-recaptcha-response' => 'dummy',
            'app_procuration_request' => [
                'electionRounds' => ['5'],
                'reason' => ProcurationRequest::REASON_HEALTH,
                'authorization' => true,
            ],
        ]));

        // Redirected to thanks
        $this->assertClientIsRedirectedTo('/procuration/je-demande/'.ProcurationRequest::STEP_URI_THANKS, $this->client);

        $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        // Procuration request should have been saved
        /* @var ProcurationRequest $request */
        $this->assertCount(6, $requests = $this->procurationRequestRepostitory->findAll());
        $this->assertInstanceOf(ProcurationRequest::class, $request = end($requests));

        $this->assertSame('FR', $request->getVoteCountry());
        $this->assertSame('92110', $request->getVotePostalCode());
        $this->assertSame('Clichy', $request->getVoteCityName());
        $this->assertSame('TestOfficeName', $request->getVoteOffice());
        $this->assertSame('male', $request->getGender());
        $this->assertSame('Paul, Jean, Martin', $request->getFirstNames());
        $this->assertSame('Dupont', $request->getLastName());
        $this->assertSame('timothe.baume@example.gb', $request->getEmailAddress());
        $this->assertSame('FR', $request->getCountry());
        $this->assertSame('69001', $request->getPostalCode());
        $this->assertSame('Lyon 1er', $request->getCityName());
        $this->assertSame('6 rue Neyret', $request->getAddress());
        $this->assertFalse($request->getElectionPresidentialFirstRound());
        $this->assertFalse($request->getElectionPresidentialSecondRound());
        $this->assertFalse($request->getElectionLegislativeFirstRound());
        $this->assertFalse($request->getElectionLegislativeSecondRound());
        $this->assertEquals([$this->getRepository(ElectionRound::class)->find(5)], $request->getElectionRounds()->toArray());
        $this->assertSame(ProcurationRequest::REASON_HEALTH, $request->getReason());
    }

    public function testProcurationRequestAsAdherent()
    {
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $this->setElectionContext();

        $procurationRequest = new ProcurationRequest();

        // Request should have been hydrated by user data
        $procurationRequest->setGender('female');
        $procurationRequest->setFirstNames('Lucie');
        $procurationRequest->setLastName('Olivera');
        $procurationRequest->setEmailAddress('luciole1989@spambox.fr');
        $procurationRequest->setAddress('13 boulevard des Italiens');
        $procurationRequest->setCountry('FR');
        $procurationRequest->setPostalCode('75009');
        $procurationRequest->setCity('75009-75109');
        $procurationRequest->setCityName('');
        $procurationRequest->setPhone($this->createPhoneNumber('33', '727363643'));
        $procurationRequest->setBirthdate(date_create_from_format('Y m d His', '1989 9 17 000000'));

        $this->assertCurrentProcurationRequestSameAs($procurationRequest);
    }

    public function testProcurationProposalNeedsElectionContext()
    {
        $this->client->request(Request::METHOD_GET, '/procuration/je-propose');

        $this->assertClientIsRedirectedTo('/procuration/choisir/'.ElectionContext::ACTION_PROPOSAL, $this->client);
    }

    public function testProcurationProposal()
    {
        $this->setElectionContext(ElectionContext::ACTION_PROPOSAL);

        // There should not be any proposal at the moment
        $this->assertCount(3, $this->procurationProxyRepostitory->findAll());

        // Initial form
        $crawler = $this->client->request(Request::METHOD_GET, '/procuration/je-propose?uuid='.LoadAdherentData::ADHERENT_8_UUID);

        $this->isSuccessful($this->client->getResponse());

        $crawler = $this->client->submit($crawler->selectButton('Je continue')->form([
            'g-recaptcha-response' => 'dummy',
            'app_procuration_proposal' => [
                'gender' => 'male',
                'firstNames' => 'Paul, Jean, Martin',
                'lastName' => 'Dupont',
                'emailAddress' => 'maxime.michaux@example.fr',
                'address' => '6 rue Neyret',
                'country' => 'FR',
                'postalCode' => '69001',
                'city' => '69001-69381',
                'cityName' => '',
                'phone' => [
                    'country' => 'FR',
                    'number' => '',
                ],
                'birthdate' => [
                    'year' => '1950',
                    'month' => '1',
                    'day' => '20',
                ],
                'voteCountry' => 'FR',
                'votePostalCode' => '92110',
                'voteCity' => '92110-92024',
                'voteCityName' => '',
                'voteOffice' => 'TestOfficeName',
                'electionRounds' => [],
                'conditions' => true,
                'authorization' => true,
            ],
        ]));

        $this->isSuccessful($this->client->getResponse());
        $this->assertCount(0, $crawler->filter('.form--warning'));
        $this->assertCount(2, $errors = $crawler->filter('.form__error'));
        $this->assertSame('Le numéro de téléphone est obligatoire.', $errors->eq(0)->text());
        $this->assertSame('Vous devez choisir au moins un tour d\'élection.', $errors->eq(1)->text());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'g-recaptcha-response' => 'dummy',
            'app_procuration_proposal' => [
                'gender' => 'male',
                'firstNames' => 'Paul, Jean, Martin',
                'lastName' => 'Dupont',
                'emailAddress' => 'maxime.michaux@example.fr',
                'address' => '6 rue Neyret',
                'country' => 'FR',
                'postalCode' => '69001',
                'city' => '69001-69381',
                'cityName' => '',
                'phone' => [
                    'country' => 'FR',
                    'number' => '0140998080',
                ],
                'birthdate' => [
                    'year' => '1950',
                    'month' => '1',
                    'day' => '20',
                ],
                'voteCountry' => 'FR',
                'votePostalCode' => '92110',
                'voteCity' => '92110-92024',
                'voteCityName' => '',
                'voteOffice' => 'TestOfficeName',
                'electionRounds' => ['5'],
                'conditions' => true,
            ],
        ]));

        // Redirected to thanks
        $this->assertClientIsRedirectedTo('/procuration/je-propose/merci?uuid='.LoadAdherentData::ADHERENT_8_UUID, $this->client);

        $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        // Procuration request should have been saved
        /* @var ProcurationProxy $proposal */
        $this->assertCount(4, $proposals = $this->procurationProxyRepostitory->findAll());
        $this->assertInstanceOf(ProcurationProxy::class, $proposal = end($proposals));

        $this->assertSame('FR', $proposal->getVoteCountry());
        $this->assertSame('92110', $proposal->getVotePostalCode());
        $this->assertSame('Clichy', $proposal->getVoteCityName());
        $this->assertSame('TestOfficeName', $proposal->getVoteOffice());
        $this->assertSame('male', $proposal->getGender());
        $this->assertSame('Paul, Jean, Martin', $proposal->getFirstNames());
        $this->assertSame('Dupont', $proposal->getLastName());
        $this->assertSame('maxime.michaux@example.fr', $proposal->getEmailAddress());
        $this->assertSame('FR', $proposal->getCountry());
        $this->assertSame('69001', $proposal->getPostalCode());
        $this->assertSame('Lyon 1er', $proposal->getCityName());
        $this->assertSame('6 rue Neyret', $proposal->getAddress());
        $this->assertFalse($proposal->getElectionPresidentialFirstRound());
        $this->assertFalse($proposal->getElectionPresidentialSecondRound());
        $this->assertFalse($proposal->getElectionLegislativeFirstRound());
        $this->assertFalse($proposal->getElectionLegislativeSecondRound());
        $this->assertEquals([$this->getRepository(ElectionRound::class)->find(5)], $proposal->getElectionRounds()->toArray());
    }

    public function testProcurationRequestNotUniqueEmailBirthDate()
    {
        $this->assertCount(5, $this->procurationRequestRepostitory->findAll());

        $this->setElectionContext();

        // Initial form
        $crawler = $this->client->request(Request::METHOD_GET, '/procuration/je-demande/'.ProcurationRequest::STEP_URI_VOTE);

        $this->isSuccessful($this->client->getResponse());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'app_procuration_request' => [
                'voteCountry' => 'FR',
                'votePostalCode' => '75018',
                'voteCity' => '75018-75118',
                'voteCityName' => '',
                'voteOffice' => 'TestOfficeName',
            ],
        ]));

        $this->assertClientIsRedirectedTo('/procuration/je-demande/'.ProcurationRequest::STEP_URI_PROFILE, $this->client);

        // Profile
        $crawler = $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'app_procuration_request' => [
                'gender' => 'female',
                'firstNames' => 'Carine, Margaux',
                'lastName' => 'Édouard',
                'emailAddress' => 'caroline.edouard@example.fr',
                'address' => '165 rue Marcadet',
                'country' => 'FR',
                'postalCode' => '75018',
                'city' => '75018-75118',
                'cityName' => '',
                'phone' => [
                    'country' => 'FR',
                    'number' => '0600010203',
                ],
                'birthdate' => [
                    'year' => '1968',
                    'month' => '10',
                    'day' => '9',
                ],
            ],
        ]));

        $this->assertClientIsRedirectedTo('/procuration/je-demande/'.ProcurationRequest::STEP_URI_ELECTION_ROUNDS, $this->client);

        // Profile
        $crawler = $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'g-recaptcha-response' => 'dummy',
            'app_procuration_request' => [
                'electionRounds' => ['5'],
                'reason' => ProcurationRequest::REASON_HEALTH,
                'authorization' => true,
            ],
        ]));

        // Redirected to thanks
        $this->assertClientIsRedirectedTo('/procuration/je-demande/merci', $this->client);

        $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        // Procuration request should have been saved
        $this->assertCount(6, $this->procurationRequestRepostitory->findAll());
    }

    public function testProcurationProposalNotUniqueEmailBirthdate()
    {
        // There should not be any proposal at the moment
        $this->assertCount(3, $this->procurationProxyRepostitory->findAll());

        $this->setElectionContext();

        // Initial form
        $crawler = $this->client->request(Request::METHOD_GET, '/procuration/je-propose?uuid='.LoadAdherentData::ADHERENT_8_UUID);

        $this->isSuccessful($this->client->getResponse());

        $this->client->submit($crawler->selectButton('Je continue')->form([
            'g-recaptcha-response' => 'dummy',
            'app_procuration_proposal' => [
                'gender' => 'male',
                'firstNames' => 'Maxime',
                'lastName' => 'Michaux',
                'emailAddress' => 'maxime.michaux@example.fr',
                'address' => '14 rue Jules Ferry',
                'country' => 'FR',
                'postalCode' => '75018',
                'city' => '75018-75120',
                'cityName' => '',
                'phone' => [
                    'country' => 'FR',
                    'number' => '0140998080',
                ],
                'birthdate' => [
                    'year' => '1989',
                    'month' => '2',
                    'day' => '17',
                ],
                'voteCountry' => 'FR',
                'votePostalCode' => '75018',
                'voteCity' => '75018-75120',
                'voteCityName' => '',
                'voteOffice' => 'Mairie',
                'electionRounds' => ['5'],
                'conditions' => true,
                'authorization' => true,
            ],
        ]));

        // Redirected to thanks
        $this->assertClientIsRedirectedTo('/procuration/je-propose/merci?uuid='.LoadAdherentData::ADHERENT_8_UUID, $this->client);

        $this->client->followRedirect();

        $this->isSuccessful($this->client->getResponse());

        // Procuration request should have been saved
        $this->assertCount(4, $this->procurationProxyRepostitory->findAll());
    }

    public function testProcurationProposalManagerUuid()
    {
        $this->setElectionContext();

        $this->client->request(Request::METHOD_GET, '/procuration/je-propose?uuid='.LoadAdherentData::ADHERENT_4_UUID);

        $this->isSuccessful($this->client->getResponse());
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadAdherentData::class,
            LoadHomeBlockData::class,
            LoadProcurationData::class,
            LoadElectionData::class,
        ]);

        $this->procurationRequestRepostitory = $this->getProcurationRequestRepository();
        $this->procurationProxyRepostitory = $this->getProcurationProxyRepository();
    }

    protected function tearDown()
    {
        $this->kill();

        $this->procurationRequestRepostitory = null;
        $this->procurationProxyRepostitory = null;

        parent::tearDown();
    }

    private function setElectionContext(string $action = ElectionContext::ACTION_REQUEST): void
    {
        if (!in_array($action, [ElectionContext::ACTION_REQUEST, ElectionContext::ACTION_PROPOSAL])) {
            throw new \InvalidArgumentException(sprintf('$action must be "%s" or "%s"', ElectionContext::ACTION_REQUEST, ElectionContext::ACTION_PROPOSAL));
        }

        $crawler = $this->client->request(Request::METHOD_GET, "/procuration/choisir/$action");

        $this->client->submit($crawler->selectButton('Continuer')->form(['election_context[elections]' => [3]]));

        $path = ElectionContext::ACTION_REQUEST === $action ? 'je-demande/'.ProcurationRequest::STEP_URI_VOTE : 'je-propose';

        $this->assertClientIsRedirectedTo("/procuration/$path", $this->client);

        $this->client->followRedirect();
    }

    private function assertCurrentProcurationRequestSameAs(ProcurationRequest $request): void
    {
        $this->assertEquals($this->client->getContainer()->get(ProcurationSession::class)->getCurrentRequest(), $request);
    }

    private function createPhoneNumber(string $country, string $number): PhoneNumber
    {
        $phone = new PhoneNumber();
        $phone->setCountryCode($country);
        $phone->setNationalNumber($number);

        return $phone;
    }
}

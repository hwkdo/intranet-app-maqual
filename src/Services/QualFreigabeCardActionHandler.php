<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Hwkdo\IntranetAppMaqual\Support\Geld;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Hwkdo\IntranetAppMaqual\Support\QualFreigabeAdaptiveCard;
use Hwkdo\IntranetAppTeamsBot\Data\TeamsAdaptiveCardAction;
use Hwkdo\IntranetAppTeamsBot\Interfaces\TeamsAdaptiveCardActionHandlerInterface;
use Hwkdo\IntranetAppTeamsBot\Support\TeamsAdaptiveCardInvokeResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class QualFreigabeCardActionHandler implements TeamsAdaptiveCardActionHandlerInterface
{
    public function __construct(
        private readonly QualWorkflow $workflow,
        private readonly QualPolicy $policy,
    ) {}

    public function supports(TeamsAdaptiveCardAction $action): bool
    {
        return in_array($action->verb, [
            QualFreigabeAdaptiveCard::VERB_APPROVE,
            QualFreigabeAdaptiveCard::VERB_REJECT,
            QualFreigabeAdaptiveCard::VERB_REFRESH,
        ], true);
    }

    public function handle(Authenticatable $user, TeamsAdaptiveCardAction $action): array
    {
        $userClass = MaqualModels::user();

        if (! $user instanceof $userClass || ! $user instanceof Model) {
            return TeamsAdaptiveCardInvokeResponse::message('Ungültiger Benutzer.');
        }

        $qualId = $action->data['qual_id'] ?? null;

        if (! is_numeric($qualId)) {
            return TeamsAdaptiveCardInvokeResponse::message('Qualifizierung nicht gefunden.');
        }

        $qual = Qual::query()->find((int) $qualId);

        if ($qual === null) {
            return TeamsAdaptiveCardInvokeResponse::message('Qualifizierung nicht gefunden.');
        }

        if ($action->verb === QualFreigabeAdaptiveCard::VERB_REFRESH) {
            return $this->refresh($user, $qual, $action);
        }

        if (! $this->darfEntscheiden($user, $qual)) {
            return TeamsAdaptiveCardInvokeResponse::card(
                QualFreigabeAdaptiveCard::statusCard(
                    $qual,
                    'Keine offene Freigabe',
                    $qual->workflowStand(),
                ),
            );
        }

        if ($action->verb === QualFreigabeAdaptiveCard::VERB_REJECT) {
            return $this->ablehnen($user, $qual, $action);
        }

        if ($this->policy->alsGf($user, $qual)) {
            return $this->gfAnnehmen($user, $qual, $action);
        }

        return $this->freigeben($user, $qual, $action);
    }

    private function refresh(Model $user, Qual $qual, TeamsAdaptiveCardAction $action): array
    {
        if ($this->darfEntscheiden($user, $qual)) {
            return TeamsAdaptiveCardInvokeResponse::card(
                QualFreigabeAdaptiveCard::forQual($qual, $this->refreshRecipientIdFromAction($action)),
            );
        }

        return TeamsAdaptiveCardInvokeResponse::card(
            QualFreigabeAdaptiveCard::statusCard($qual, 'Aktueller Stand', $qual->workflowStand()),
        );
    }

    private function freigeben(Model $user, Qual $qual, TeamsAdaptiveCardAction $action): array
    {
        try {
            $qual = match (true) {
                $this->policy->alsAl($user, $qual) => $this->workflow->alZustimmen($qual, $user),
                $this->policy->alsPersonal($user, $qual) => $this->workflow->persoGenehmigen($qual, $user),
                default => null,
            };
        } catch (WorkflowException $exception) {
            return $this->mitHinweis($qual, $action, $exception->getMessage());
        }

        if (! $qual instanceof Qual) {
            return TeamsAdaptiveCardInvokeResponse::message('Diese Freigabe ist nicht mehr offen.');
        }

        $headline = $qual->status === QualStatus::Genehmigt ? 'Genehmigt' : 'Zugestimmt';

        return TeamsAdaptiveCardInvokeResponse::card(
            QualFreigabeAdaptiveCard::statusCard($qual, $headline, $qual->workflowStand()),
        );
    }

    private function ablehnen(Model $user, Qual $qual, TeamsAdaptiveCardAction $action): array
    {
        $grund = trim((string) ($action->data['ablehnenGrund'] ?? ''));

        if (mb_strlen($grund) < 3) {
            return $this->mitHinweis($qual, $action, 'Bitte einen Ablehnungsgrund mit mindestens 3 Zeichen angeben.');
        }

        try {
            $qual = match (true) {
                $this->policy->alsAl($user, $qual) => $this->workflow->alAblehnen($qual, $user, $grund),
                $this->policy->alsGf($user, $qual) => $this->workflow->gfAblehnen($qual, $user, $grund),
                $this->policy->alsPersonal($user, $qual) => $this->workflow->persoAblehnen($qual, $user, $grund),
                default => null,
            };
        } catch (WorkflowException $exception) {
            return $this->mitHinweis($qual, $action, $exception->getMessage());
        }

        if (! $qual instanceof Qual) {
            return TeamsAdaptiveCardInvokeResponse::message('Diese Freigabe ist nicht mehr offen.');
        }

        return TeamsAdaptiveCardInvokeResponse::card(
            QualFreigabeAdaptiveCard::statusCard(
                $qual,
                'Abgelehnt',
                'Die Qualifizierung „'.$qual->name.'“ wurde abgelehnt.',
            ),
        );
    }

    /**
     * @return array{statusCode: int, type: string, value: array<string, mixed>}
     */
    private function mitHinweis(Qual $qual, TeamsAdaptiveCardAction $action, string $hinweis): array
    {
        $card = QualFreigabeAdaptiveCard::forQual($qual, $this->refreshRecipientIdFromAction($action));
        array_unshift($card['body'], [
            'type' => 'TextBlock',
            'text' => $hinweis,
            'color' => 'Attention',
            'wrap' => true,
        ]);

        return TeamsAdaptiveCardInvokeResponse::card($card);
    }

    private function darfEntscheiden(Model $user, Qual $qual): bool
    {
        return $this->policy->alsAl($user, $qual)
            || $this->policy->alsGf($user, $qual)
            || $this->policy->alsPersonal($user, $qual);
    }

    private function gfAnnehmen(Model $user, Qual $qual, TeamsAdaptiveCardAction $action): array
    {
        $argumente = $this->gfArgumente($qual, $action);

        if (is_string($argumente)) {
            return $this->mitHinweis($qual, $action, $argumente);
        }

        try {
            $qual = $this->workflow->gfZustimmen($qual, $user, $argumente['kosten'], $argumente['grund']);
        } catch (WorkflowException $exception) {
            return $this->mitHinweis($qual, $action, $exception->getMessage());
        }

        return TeamsAdaptiveCardInvokeResponse::card(
            QualFreigabeAdaptiveCard::statusCard(
                $qual,
                $argumente['kosten'] !== null ? 'Mit geänderten Kosten angenommen' : 'Zugestimmt',
                $qual->workflowStand(),
            ),
        );
    }

    /**
     * @return array{kosten: ?string, grund: ?string}|string
     */
    private function gfArgumente(Qual $qual, TeamsAdaptiveCardAction $action): array|string
    {
        $entscheidung = trim((string) ($action->data['entscheidung'] ?? ''));
        $roh = trim((string) ($action->data['neueKosten'] ?? ''));
        $willAendern = $entscheidung === 'aendern' || ($entscheidung !== 'annehmen' && $roh !== '');

        if (! $willAendern) {
            return ['kosten' => null, 'grund' => null];
        }

        if ($roh === '') {
            return 'Bitte den neuen Betrag eintragen.';
        }

        $neu = Geld::normalisieren($roh);

        if (! is_numeric($neu)) {
            return 'Bitte den neuen Betrag als Zahl eintragen, zum Beispiel 450,00.';
        }

        if ($neu === Geld::normalisieren((string) $qual->kosten_voraussichtlich)) {
            return 'Dieser Betrag entspricht den beantragten Kosten. Wählen Sie „Annehmen“, wenn die Kosten bleiben sollen.';
        }

        $grund = trim((string) ($action->data['kostenGrund'] ?? ''));

        if (mb_strlen($grund) < 3) {
            return 'Bitte eine Begründung für die Kostenänderung angeben.';
        }

        return ['kosten' => $neu, 'grund' => $grund];
    }

    private function refreshRecipientIdFromAction(TeamsAdaptiveCardAction $action): ?string
    {
        $fromId = $action->activity['from']['id'] ?? null;

        if (is_string($fromId) && $fromId !== '') {
            return $fromId;
        }

        return $action->azureUserId !== '' ? $action->azureUserId : null;
    }
}

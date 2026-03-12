<?php
require_once __DIR__ . '/../models/Target.php';
require_once __DIR__ . '/../models/Objective.php';
require_once __DIR__ . '/../models/Decision.php';
require_once __DIR__ . '/../models/PlanAction.php';

class TargetController
{
    public function index(): void
    {
        $userId = current_user_id();
        $targetModel = new Target();

        view('targets/index', [
            'title' => 'Alvos, Objetivos e Execucao',
            'targets' => $targetModel->allByUser($userId),
            'activeTarget' => $targetModel->activeByUser($userId),
        ]);
    }

    public function show(): void
    {
        $userId = current_user_id();
        $targetId = (int)($_GET['id'] ?? 0);

        $targetModel = new Target();
        $objectiveModel = new Objective();
        $decisionModel = new Decision();
        $actionModel = new PlanAction();

        $target = $targetModel->find($targetId, $userId);
        if (!$target) {
            flash('error', 'Alvo nao encontrado.');
            redirect('index.php?route=targets');
        }

        $objectives = $objectiveModel->byTarget($targetId, $userId);
        foreach ($objectives as &$objective) {
            $decisions = $decisionModel->byObjective((int)$objective['id'], $userId);
            foreach ($decisions as &$decision) {
                $decision['actions'] = $actionModel->byDecision((int)$decision['id'], $userId);
            }
            $objective['decisions'] = $decisions;
        }

        view('targets/show', [
            'title' => 'Detalhes do Alvo',
            'target' => $target,
            'objectives' => $objectives,
        ]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo do alvo e obrigatorio.');
            redirect('index.php?route=targets');
        }

        $data = [
            'user_id' => current_user_id(),
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'target_amount' => $this->parseDecimalInput($_POST['target_amount'] ?? ''),
            'status' => $this->normalizeTargetStatus($_POST['status'] ?? 'paused'),
            'start_date' => $this->nullableDate($_POST['start_date'] ?? ''),
            'expected_end_date' => $this->nullableDate($_POST['expected_end_date'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        (new Target())->create($data);

        flash('success', 'Alvo criado com sucesso.');
        redirect('index.php?route=targets');
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $id = (int)($_POST['id'] ?? 0);
        $target = (new Target())->find($id, $userId);
        if (!$target) {
            flash('error', 'Alvo nao encontrado.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo do alvo e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . $id);
        }

        $data = [
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'target_amount' => $this->parseDecimalInput($_POST['target_amount'] ?? ''),
            'status' => $this->normalizeTargetStatus($_POST['status'] ?? (string)$target['status']),
            'start_date' => $this->nullableDate($_POST['start_date'] ?? ''),
            'expected_end_date' => $this->nullableDate($_POST['expected_end_date'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        (new Target())->update($id, $userId, $data);

        flash('success', 'Alvo atualizado com sucesso.');
        redirect('index.php?route=targets_show&id=' . $id);
    }

    public function delete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $id = (int)($_POST['id'] ?? 0);
        (new Target())->delete($id, current_user_id());

        flash('success', 'Alvo removido com sucesso.');
        redirect('index.php?route=targets');
    }

    public function setStatus(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $id = (int)($_POST['id'] ?? 0);
        $status = $this->normalizeTargetStatus($_POST['status'] ?? 'paused');

        $target = (new Target())->find($id, $userId);
        if (!$target) {
            flash('error', 'Alvo nao encontrado.');
            redirect('index.php?route=targets');
        }

        (new Target())->setStatus($id, $userId, $status);

        flash('success', 'Status do alvo atualizado.');
        redirect('index.php?route=targets_show&id=' . $id);
    }

    public function objectiveStore(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $targetId = (int)($_POST['target_id'] ?? 0);
        $target = (new Target())->find($targetId, $userId);
        if (!$target) {
            flash('error', 'Alvo nao encontrado para cadastrar objetivo.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo do objetivo e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . $targetId);
        }

        $termMonths = max(1, (int)($_POST['term_months'] ?? 3));

        $objectiveId = (new Objective())->create($userId, [
            'target_id' => $targetId,
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'status' => $this->normalizeObjectiveStatus($_POST['status'] ?? 'adjusted'),
            'start_date' => $this->nullableDate($_POST['start_date'] ?? ''),
            'term_months' => $termMonths,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        if ($objectiveId <= 0) {
            flash('error', 'Nao foi possivel criar objetivo para este alvo.');
            redirect('index.php?route=targets_show&id=' . $targetId);
        }

        flash('success', 'Objetivo criado com sucesso.');
        redirect('index.php?route=targets_show&id=' . $targetId);
    }

    public function objectiveUpdate(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $objectiveId = (int)($_POST['id'] ?? 0);
        $objectiveModel = new Objective();
        $objective = $objectiveModel->findForUser($objectiveId, $userId);
        if (!$objective) {
            flash('error', 'Objetivo nao encontrado.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo do objetivo e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
        }

        $objectiveModel->update($objectiveId, $userId, [
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'status' => $this->normalizeObjectiveStatus($_POST['status'] ?? (string)$objective['status']),
            'start_date' => $this->nullableDate($_POST['start_date'] ?? ''),
            'term_months' => max(1, (int)($_POST['term_months'] ?? 3)),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        flash('success', 'Objetivo atualizado.');
        redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
    }

    public function objectiveDelete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $objectiveId = (int)($_POST['id'] ?? 0);
        $objectiveModel = new Objective();
        $objective = $objectiveModel->findForUser($objectiveId, $userId);
        if (!$objective) {
            flash('error', 'Objetivo nao encontrado.');
            redirect('index.php?route=targets');
        }

        $targetId = (int)$objective['target_id'];
        $objectiveModel->delete($objectiveId, $userId);

        flash('success', 'Objetivo excluido.');
        redirect('index.php?route=targets_show&id=' . $targetId);
    }

    public function objectiveSetStatus(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $objectiveId = (int)($_POST['id'] ?? 0);
        $status = $this->normalizeObjectiveStatus($_POST['status'] ?? 'adjusted');

        $objectiveModel = new Objective();
        $objective = $objectiveModel->findForUser($objectiveId, $userId);
        if (!$objective) {
            flash('error', 'Objetivo nao encontrado.');
            redirect('index.php?route=targets');
        }

        $objectiveModel->setStatus($objectiveId, $userId, $status);

        flash('success', 'Status do objetivo atualizado.');
        redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
    }

    public function decisionStore(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $objectiveId = (int)($_POST['objective_id'] ?? 0);
        $objective = (new Objective())->findForUser($objectiveId, $userId);
        if (!$objective) {
            flash('error', 'Objetivo nao encontrado para cadastrar decisao.');
            redirect('index.php?route=targets');
        }

        $decisionModel = new Decision();
        $currentCount = $decisionModel->countByObjective($objectiveId, $userId);
        if ($currentCount >= 3) {
            flash('error', 'Cada objetivo permite no maximo 3 decisoes.');
            redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo da decisao e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
        }

        $decisionId = $decisionModel->create($userId, [
            'objective_id' => $objectiveId,
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'order_no' => max(1, (int)($_POST['order_no'] ?? 0)),
            'status' => $this->normalizeDecisionStatus($_POST['status'] ?? 'pending'),
        ]);

        if ($decisionId <= 0) {
            flash('error', 'Nao foi possivel criar decisao para este objetivo.');
            redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
        }

        flash('success', 'Decisao criada com sucesso.');
        redirect('index.php?route=targets_show&id=' . (int)$objective['target_id']);
    }

    public function decisionUpdate(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $decisionId = (int)($_POST['id'] ?? 0);
        $decisionModel = new Decision();
        $decision = $decisionModel->findForUser($decisionId, $userId);
        if (!$decision) {
            flash('error', 'Decisao nao encontrada.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo da decisao e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . (int)$decision['target_id']);
        }

        $decisionModel->update($decisionId, $userId, [
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'order_no' => max(1, (int)($_POST['order_no'] ?? 1)),
            'status' => $this->normalizeDecisionStatus($_POST['status'] ?? (string)$decision['status']),
        ]);

        flash('success', 'Decisao atualizada.');
        redirect('index.php?route=targets_show&id=' . (int)$decision['target_id']);
    }

    public function decisionDelete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $decisionId = (int)($_POST['id'] ?? 0);
        $decisionModel = new Decision();
        $decision = $decisionModel->findForUser($decisionId, $userId);
        if (!$decision) {
            flash('error', 'Decisao nao encontrada.');
            redirect('index.php?route=targets');
        }

        $decisionModel->delete($decisionId, $userId);

        flash('success', 'Decisao excluida.');
        redirect('index.php?route=targets_show&id=' . (int)$decision['target_id']);
    }

    public function actionStore(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $decisionId = (int)($_POST['decision_id'] ?? 0);
        $decision = (new Decision())->findForUser($decisionId, $userId);
        if (!$decision) {
            flash('error', 'Decisao nao encontrada para cadastrar acao.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo da acao e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . (int)$decision['target_id']);
        }

        $actionId = (new PlanAction())->create($userId, [
            'decision_id' => $decisionId,
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'planned_date' => $this->nullableDate($_POST['planned_date'] ?? ''),
            'status' => $this->normalizeActionStatus($_POST['status'] ?? 'pending'),
            'is_done' => !empty($_POST['is_done']),
            'completed_at' => $this->nullableDate($_POST['completed_at'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        if ($actionId <= 0) {
            flash('error', 'Nao foi possivel criar acao para esta decisao.');
            redirect('index.php?route=targets_show&id=' . (int)$decision['target_id']);
        }

        flash('success', 'Acao criada com sucesso.');
        redirect('index.php?route=targets_show&id=' . (int)$decision['target_id']);
    }

    public function actionUpdate(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $actionId = (int)($_POST['id'] ?? 0);
        $actionModel = new PlanAction();
        $action = $actionModel->findForUser($actionId, $userId);
        if (!$action) {
            flash('error', 'Acao nao encontrada.');
            redirect('index.php?route=targets');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Titulo da acao e obrigatorio.');
            redirect('index.php?route=targets_show&id=' . (int)$action['target_id']);
        }

        $actionModel->update($actionId, $userId, [
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'planned_date' => $this->nullableDate($_POST['planned_date'] ?? ''),
            'status' => $this->normalizeActionStatus($_POST['status'] ?? (string)$action['status']),
            'is_done' => !empty($_POST['is_done']),
            'completed_at' => $this->nullableDate($_POST['completed_at'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        flash('success', 'Acao atualizada.');
        redirect('index.php?route=targets_show&id=' . (int)$action['target_id']);
    }

    public function actionDelete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $actionId = (int)($_POST['id'] ?? 0);
        $actionModel = new PlanAction();
        $action = $actionModel->findForUser($actionId, $userId);
        if (!$action) {
            flash('error', 'Acao nao encontrada.');
            redirect('index.php?route=targets');
        }

        $actionModel->delete($actionId, $userId);

        flash('success', 'Acao excluida.');
        redirect('index.php?route=targets_show&id=' . (int)$action['target_id']);
    }

    public function actionToggleDone(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=targets');
        }

        $userId = current_user_id();
        $actionId = (int)($_POST['id'] ?? 0);
        $done = !empty($_POST['done']);

        $actionModel = new PlanAction();
        $action = $actionModel->findForUser($actionId, $userId);
        if (!$action) {
            flash('error', 'Acao nao encontrada.');
            redirect('index.php?route=targets');
        }

        $actionModel->markDone($actionId, $userId, $done);

        flash('success', $done ? 'Acao marcada como realizada.' : 'Acao reaberta como pendente.');
        redirect('index.php?route=targets_show&id=' . (int)$action['target_id']);
    }

    private function nullableDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function parseDecimalInput($value): ?float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        $raw = str_replace(['R$', ' '], '', $raw);

        $hasComma = strpos($raw, ',') !== false;
        $hasDot = strpos($raw, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
        }

        $raw = preg_replace('/[^0-9\.\-]/', '', $raw);
        if (!is_string($raw) || $raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (float)$raw;
    }

    private function normalizeTargetStatus(string $status): string
    {
        $allowed = ['active', 'achieved', 'paused', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'paused';
    }

    private function normalizeObjectiveStatus(string $status): string
    {
        $allowed = ['active', 'finished', 'adjusted', 'achieved'];
        return in_array($status, $allowed, true) ? $status : 'adjusted';
    }

    private function normalizeDecisionStatus(string $status): string
    {
        $allowed = ['pending', 'in_progress', 'done', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'pending';
    }

    private function normalizeActionStatus(string $status): string
    {
        $allowed = ['pending', 'in_progress', 'completed', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'pending';
    }
}

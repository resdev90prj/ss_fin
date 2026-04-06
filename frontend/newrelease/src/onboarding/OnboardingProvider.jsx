import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { apiRequest } from '../lib/apiClient';
import { getEffectiveModules } from '../lib/access';
import OnboardingTour from './OnboardingTour';
import {
  filterOnboardingChecklistConfig,
  filterOnboardingTourSteps,
  onboardingTourSteps,
} from './onboardingConfig';

const OnboardingContext = createContext({
  checklist: {
    items: [],
    stats: {},
    completedCount: 0,
    totalCount: 0,
    percent: 0,
    nextItemKey: null,
    loading: true,
    error: '',
  },
  steps: onboardingTourSteps,
  isTourOpen: false,
  currentStepIndex: 0,
  currentStep: onboardingTourSteps[0],
  startTour: () => {},
  nextTourStep: () => {},
  skipTour: () => {},
  finishTour: () => {},
  refreshProgress: async () => {},
});

function buildChecklistState(stats = {}, extra = {}) {
  const checklistConfig = Array.isArray(extra.checklistConfig) ? extra.checklistConfig : [];
  const items = checklistConfig.map((item) => ({
    ...item,
    completed: item.isComplete(stats),
  }));
  const completedCount = items.filter((item) => item.completed).length;
  const nextItem = items.find((item) => !item.completed) || null;

  return {
    items: items.map((item) => ({
      ...item,
      isNextStep: nextItem?.key === item.key,
    })),
    stats,
    completedCount,
    totalCount: items.length,
    percent: items.length > 0 ? Math.round((completedCount / items.length) * 100) : 0,
    nextItemKey: nextItem?.key || null,
    loading: false,
    error: '',
    ...extra,
  };
}

function emptyChecklistState(checklistConfig = []) {
  return buildChecklistState({}, { checklistConfig, loading: true });
}

function onboardingStorageKey(session) {
  const effectiveUserId = session.scope?.current_user_id || session.scope?.scoped_user_id || session.user?.id || 'anon';
  return `ssfin:onboarding:${effectiveUserId}:react-update-tour:v2`;
}

export function OnboardingProvider({ children }) {
  const { session } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [checklist, setChecklist] = useState(emptyChecklistState);
  const [isTourOpen, setIsTourOpen] = useState(false);
  const [currentStepIndex, setCurrentStepIndex] = useState(0);
  const [tourCompleted, setTourCompleted] = useState(true);
  const [autoStartPending, setAutoStartPending] = useState(false);
  const [queuedStepIndex, setQueuedStepIndex] = useState(null);
  const availableModules = useMemo(() => getEffectiveModules(session), [session]);
  const steps = useMemo(() => filterOnboardingTourSteps(availableModules), [availableModules]);
  const checklistConfig = useMemo(() => filterOnboardingChecklistConfig(availableModules), [availableModules]);

  const storageKey = useMemo(() => onboardingStorageKey(session), [session]);
  const isAuthenticated = Boolean(session.authenticated);
  const currentStep = steps[currentStepIndex] || steps[0] || null;

  function markTourAsSeen() {
    window.localStorage.setItem(storageKey, 'done');
    setTourCompleted(true);
  }

  async function refreshProgress(signal) {
    if (!isAuthenticated) {
      setChecklist(emptyChecklistState(checklistConfig));
      return;
    }

    setChecklist((current) => ({
      ...current,
      loading: current.items.length === 0,
      error: '',
    }));

    try {
      const response = await apiRequest('/onboarding/summary', { signal });
      setChecklist(buildChecklistState(response.data?.stats || {}, { checklistConfig }));
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setChecklist((current) => ({
          ...current,
          loading: false,
          error: requestError.message || 'Nao foi possivel carregar o onboarding.',
        }));
      }
    }
  }

  function openTour(stepIndex = 0) {
    if (!isAuthenticated || steps.length === 0) {
      return;
    }

    if (!tourCompleted) {
      markTourAsSeen();
      setAutoStartPending(false);
    }

    if (location.pathname !== '/') {
      setQueuedStepIndex(stepIndex);
      navigate('/');
      return;
    }

    window.dispatchEvent(new CustomEvent('onboarding:tour-open'));
    setCurrentStepIndex(stepIndex);
    setIsTourOpen(true);
  }

  function closeTour() {
    setIsTourOpen(false);
    window.dispatchEvent(new CustomEvent('onboarding:tour-close'));
  }

  function nextTourStep() {
    if (currentStepIndex >= steps.length - 1) {
      closeTour();
      return;
    }

    setCurrentStepIndex((index) => Math.min(index + 1, steps.length - 1));
  }

  function skipTour() {
    closeTour();
  }

  function finishTour() {
    closeTour();
  }

  useEffect(() => {
    if (!isAuthenticated) {
      setChecklist(emptyChecklistState(checklistConfig));
      setIsTourOpen(false);
      setCurrentStepIndex(0);
      setTourCompleted(true);
      setAutoStartPending(false);
      setQueuedStepIndex(null);
      return;
    }

    const alreadySeen = window.localStorage.getItem(storageKey) === 'done';
    setTourCompleted(alreadySeen);
    setAutoStartPending(!alreadySeen && steps.length > 0);
  }, [checklistConfig, isAuthenticated, steps.length, storageKey]);

  useEffect(() => {
    if (!isAuthenticated) {
      return undefined;
    }

    const controller = new AbortController();
    refreshProgress(controller.signal);
    return () => controller.abort();
  }, [checklistConfig, isAuthenticated, location.pathname, session.scope?.current_user_id]);

  useEffect(() => {
    if (!isAuthenticated) {
      return undefined;
    }

    function handleWindowFocus() {
      refreshProgress();
    }

    window.addEventListener('focus', handleWindowFocus);
    return () => window.removeEventListener('focus', handleWindowFocus);
  }, [isAuthenticated]);

  useEffect(() => {
    if (!isAuthenticated || tourCompleted || isTourOpen || !autoStartPending || location.pathname !== '/' || steps.length === 0) {
      return undefined;
    }

    const timeoutId = window.setTimeout(() => {
      markTourAsSeen();
      window.dispatchEvent(new CustomEvent('onboarding:tour-open'));
      setCurrentStepIndex(0);
      setIsTourOpen(true);
      setAutoStartPending(false);
    }, 280);

    return () => window.clearTimeout(timeoutId);
  }, [autoStartPending, isAuthenticated, isTourOpen, location.pathname, steps.length, tourCompleted]);

  useEffect(() => {
    if (queuedStepIndex === null || location.pathname !== '/') {
      return undefined;
    }

    const timeoutId = window.setTimeout(() => {
      window.dispatchEvent(new CustomEvent('onboarding:tour-open'));
      setCurrentStepIndex(queuedStepIndex);
      setIsTourOpen(true);
      setQueuedStepIndex(null);
    }, 160);

    return () => window.clearTimeout(timeoutId);
  }, [queuedStepIndex, location.pathname]);

  useEffect(() => {
    if (steps.length === 0) {
      setCurrentStepIndex(0);
      setQueuedStepIndex(null);
      setIsTourOpen(false);
      return;
    }

    if (currentStepIndex >= steps.length) {
      setCurrentStepIndex(steps.length - 1);
    }
  }, [currentStepIndex, steps.length]);

  const value = {
    checklist,
    steps,
    isTourOpen,
    currentStepIndex,
    currentStep,
    startTour: openTour,
    nextTourStep,
    skipTour,
    finishTour,
    refreshProgress,
  };

  return (
    <OnboardingContext.Provider value={value}>
      {children}
      <OnboardingTour />
    </OnboardingContext.Provider>
  );
}

export function useOnboarding() {
  return useContext(OnboardingContext);
}

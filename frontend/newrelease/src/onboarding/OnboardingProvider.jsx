import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { apiRequest } from '../lib/apiClient';
import OnboardingTour from './OnboardingTour';
import { onboardingChecklistConfig, onboardingTourSteps } from './onboardingConfig';

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
  const items = onboardingChecklistConfig.map((item) => ({
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

function emptyChecklistState() {
  return buildChecklistState({}, { loading: true });
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

  const storageKey = useMemo(() => onboardingStorageKey(session), [session]);
  const isAuthenticated = Boolean(session.authenticated);
  const currentStep = onboardingTourSteps[currentStepIndex] || onboardingTourSteps[0];

  function markTourAsSeen() {
    window.localStorage.setItem(storageKey, 'done');
    setTourCompleted(true);
  }

  async function refreshProgress(signal) {
    if (!isAuthenticated) {
      setChecklist(emptyChecklistState());
      return;
    }

    setChecklist((current) => ({
      ...current,
      loading: current.items.length === 0,
      error: '',
    }));

    try {
      const response = await apiRequest('/onboarding/summary', { signal });
      setChecklist(buildChecklistState(response.data?.stats || {}));
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
    if (!isAuthenticated) {
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
    if (currentStepIndex >= onboardingTourSteps.length - 1) {
      closeTour();
      return;
    }

    setCurrentStepIndex((index) => Math.min(index + 1, onboardingTourSteps.length - 1));
  }

  function skipTour() {
    closeTour();
  }

  function finishTour() {
    closeTour();
  }

  useEffect(() => {
    if (!isAuthenticated) {
      setChecklist(emptyChecklistState());
      setIsTourOpen(false);
      setCurrentStepIndex(0);
      setTourCompleted(true);
      setAutoStartPending(false);
      setQueuedStepIndex(null);
      return;
    }

    const alreadySeen = window.localStorage.getItem(storageKey) === 'done';
    setTourCompleted(alreadySeen);
    setAutoStartPending(!alreadySeen);
  }, [isAuthenticated, storageKey]);

  useEffect(() => {
    if (!isAuthenticated) {
      return undefined;
    }

    const controller = new AbortController();
    refreshProgress(controller.signal);
    return () => controller.abort();
  }, [isAuthenticated, location.pathname, session.scope?.current_user_id]);

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
    if (!isAuthenticated || tourCompleted || isTourOpen || !autoStartPending || location.pathname !== '/') {
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
  }, [autoStartPending, isAuthenticated, isTourOpen, location.pathname, tourCompleted]);

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

  const value = {
    checklist,
    steps: onboardingTourSteps,
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

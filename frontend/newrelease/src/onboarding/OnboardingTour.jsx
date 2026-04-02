import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '../components/ui/button';
import { useOnboarding } from './OnboardingProvider';

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function firstVisibleElement(selector) {
  if (!selector) {
    return null;
  }

  const matches = Array.from(document.querySelectorAll(selector));
  return matches.find((element) => {
    const rect = element.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }) || null;
}

function calculatePosition(targetRect, panelRect, placement) {
  const padding = 12;
  const gap = 16;

  if (!targetRect || window.innerWidth < 768) {
    return {
      top: window.innerHeight - panelRect.height - 16,
      left: clamp((window.innerWidth - panelRect.width) / 2, padding, window.innerWidth - panelRect.width - padding),
    };
  }

  let top = window.innerHeight - panelRect.height - 16;
  let left = clamp((window.innerWidth - panelRect.width) / 2, padding, window.innerWidth - panelRect.width - padding);

  if (placement === 'top') {
    top = targetRect.top - panelRect.height - gap;
    left = targetRect.left + ((targetRect.width - panelRect.width) / 2);
  } else if (placement === 'left') {
    top = targetRect.top + ((targetRect.height - panelRect.height) / 2);
    left = targetRect.left - panelRect.width - gap;
  } else if (placement === 'right') {
    top = targetRect.top + ((targetRect.height - panelRect.height) / 2);
    left = targetRect.right + gap;
  } else {
    top = targetRect.bottom + gap;
    left = targetRect.left + ((targetRect.width - panelRect.width) / 2);
  }

  return {
    top: clamp(top, padding, window.innerHeight - panelRect.height - padding),
    left: clamp(left, padding, window.innerWidth - panelRect.width - padding),
  };
}

export default function OnboardingTour() {
  const {
    currentStep,
    currentStepIndex,
    finishTour,
    isTourOpen,
    nextTourStep,
    skipTour,
    steps,
  } = useOnboarding();
  const [panelElement, setPanelElement] = useState(null);
  const [panelStyle, setPanelStyle] = useState({ top: 16, left: 16 });
  const [haloRect, setHaloRect] = useState(null);

  useEffect(() => {
    if (!isTourOpen || !currentStep) {
      return undefined;
    }

    const timeoutId = window.setTimeout(() => {
      const target = firstVisibleElement(currentStep.targetSelector);
      target?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    }, 120);

    return () => window.clearTimeout(timeoutId);
  }, [currentStep, isTourOpen]);

  useEffect(() => {
    if (!isTourOpen || !currentStep || !panelElement) {
      return undefined;
    }

    let activeElement = null;

    function updateLayout() {
      const target = firstVisibleElement(currentStep.targetSelector);

      if (activeElement !== target) {
        if (activeElement) {
          activeElement.removeAttribute('data-onboarding-active');
        }

        activeElement = target;
        if (activeElement) {
          activeElement.setAttribute('data-onboarding-active', 'true');
        }
      }

      if (target) {
        const rect = target.getBoundingClientRect();
        setHaloRect({
          top: Math.max(8, rect.top - 8),
          left: Math.max(8, rect.left - 8),
          width: rect.width + 16,
          height: rect.height + 16,
        });
        setPanelStyle(calculatePosition(rect, panelElement.getBoundingClientRect(), currentStep.placement));
        return;
      }

      setHaloRect(null);
      setPanelStyle(calculatePosition(null, panelElement.getBoundingClientRect(), currentStep.placement));
    }

    updateLayout();

    const intervalId = window.setInterval(updateLayout, 280);
    window.addEventListener('resize', updateLayout);
    window.addEventListener('scroll', updateLayout, true);

    return () => {
      window.clearInterval(intervalId);
      window.removeEventListener('resize', updateLayout);
      window.removeEventListener('scroll', updateLayout, true);

      if (activeElement) {
        activeElement.removeAttribute('data-onboarding-active');
      }
    };
  }, [currentStep, isTourOpen, panelElement]);

  if (!isTourOpen || !currentStep) {
    return null;
  }

  const isLastStep = currentStepIndex === steps.length - 1;

  return createPortal(
    <div className="pointer-events-none fixed inset-0 z-[80]">
      {haloRect ? (
        <div
          className="pointer-events-none fixed rounded-[30px] border border-primary/25 bg-primary/5 shadow-[0_18px_50px_rgba(37,99,235,0.12)]"
          style={haloRect}
        />
      ) : null}

      <section
        ref={setPanelElement}
        className="pointer-events-auto fixed w-[min(340px,calc(100vw-24px))] rounded-[28px] border border-slate-200 bg-white/96 p-5 shadow-[0_24px_60px_rgba(15,23,42,0.18)] backdrop-blur"
        style={panelStyle}
      >
        <div className="flex items-center justify-between gap-4">
          <span className="hero-card__eyebrow !mb-0">Tour rapido</span>
          <span className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
            {currentStepIndex + 1}/{steps.length}
          </span>
        </div>

        <h3 className="mt-4 text-lg font-semibold tracking-tight text-slate-950">{currentStep.title}</h3>
        <p className="mt-2 text-sm leading-6 text-slate-600">{currentStep.description}</p>

        <div className="mt-4 flex items-center gap-2">
          {steps.map((step, index) => (
            <span
              key={step.id}
              className={`h-1.5 rounded-full transition-all ${
                index === currentStepIndex ? 'w-8 bg-primary' : 'w-3 bg-slate-200'
              }`}
            />
          ))}
        </div>

        <div className="mt-5 flex items-center justify-between gap-3">
          <Button type="button" size="sm" variant="ghost" onClick={skipTour}>
            Pular
          </Button>

          {isLastStep ? (
            <Button type="button" size="sm" onClick={finishTour}>
              Finalizar
            </Button>
          ) : (
            <Button type="button" size="sm" onClick={nextTourStep}>
              Proximo
            </Button>
          )}
        </div>
      </section>
    </div>,
    document.body,
  );
}

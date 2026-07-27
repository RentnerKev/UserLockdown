import { type ReactNode, useEffect, useRef } from 'react'

type ModalDialogProps = {
  titleId: string
  descriptionId?: string
  closeDisabled?: boolean
  wide?: boolean
  role?: 'dialog' | 'alertdialog'
  onClose: () => void
  children: ReactNode
}

const focusableSelector = [
  'button:not(:disabled)',
  'input:not(:disabled)',
  'select:not(:disabled)',
  'textarea:not(:disabled)',
  'a[href]',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

export const ModalDialog = ({
  titleId,
  descriptionId,
  closeDisabled = false,
  wide = false,
  role = 'dialog',
  onClose,
  children,
}: ModalDialogProps) => {
  const dialogRef = useRef<HTMLElement>(null)
  const onCloseRef = useRef(onClose)
  const closeDisabledRef = useRef(closeDisabled)

  onCloseRef.current = onClose
  closeDisabledRef.current = closeDisabled

  useEffect(() => {
    const previouslyFocused = document.activeElement
    const dialog = dialogRef.current
    const firstFocusable = dialog?.querySelector<HTMLElement>(focusableSelector)
    firstFocusable?.focus()

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !closeDisabledRef.current) {
        event.preventDefault()
        onCloseRef.current()
        return
      }

      if (event.key !== 'Tab' || dialog === null) {
        return
      }

      const focusableElements = [...dialog.querySelectorAll<HTMLElement>(focusableSelector)]
      const firstElement = focusableElements.at(0)
      const lastElement = focusableElements.at(-1)

      if (firstElement === undefined || lastElement === undefined) {
        event.preventDefault()
        return
      }

      if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault()
        lastElement.focus()
      } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault()
        firstElement.focus()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => {
      document.removeEventListener('keydown', handleKeyDown)
      if (previouslyFocused instanceof HTMLElement && previouslyFocused.isConnected) {
        previouslyFocused.focus()
      }
    }
  }, [])

  return (
    <div
      className="user-lockdown-dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !closeDisabled) {
          onClose()
        }
      }}
    >
      <section
        ref={dialogRef}
        className={`user-lockdown-dialog${wide ? ' user-lockdown-dialog--wide' : ''}`}
        role={role}
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
      >
        {children}
      </section>
    </div>
  )
}

import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
  type MouseEvent as ReactMouseEvent,
} from 'react'
import { loadVeratagAdminConfig } from '../../../../config/loadVeratagAdminConfig'
import './input-select.css'

export type InputSelectOption = {
  iconClassName?: string
  iconSrc?: string
  label: string
  value: string
}

type InputSelectProps = {
  errorText?: string
  hintText?: string
  id: string
  label?: string
  onChange?: (value: string) => void
  options: InputSelectOption[]
  placeholder?: string
  value?: string
}

export function InputSelect({
  errorText,
  hintText,
  id,
  label,
  onChange,
  options,
  placeholder = 'Select an option',
  value,
}: InputSelectProps) {
  const [isOpen, setIsOpen] = useState(false)
  const rootRef = useRef<HTMLDivElement | null>(null)
  const controlRef = useRef<HTMLButtonElement | null>(null)
  const optionRefs = useRef<Array<HTMLButtonElement | null>>([])
  const openedWithKeyboard = useRef(false)
  const config = loadVeratagAdminConfig()
  const hasError = typeof errorText === 'string' && errorText.trim() !== ''
  const hintId = hintText ? `${id}-hint` : undefined
  const errorId = hasError ? `${id}-error` : undefined
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined

  const selectedOption = useMemo(
    () => options.find((option) => option.value === value),
    [options, value],
  )

  const renderOptionIcon = (option: InputSelectOption) => {
    if (option.iconClassName) {
      return (
        <span
          aria-hidden="true"
          className={`veratag-input-select__option-icon veratag-input-select__option-icon--sprite ${option.iconClassName}`}
        />
      )
    }

    if (option.iconSrc) {
      return (
        <img
          alt=""
          aria-hidden="true"
          className="veratag-input-select__option-icon"
          src={option.iconSrc}
        />
      )
    }

    return null
  }

  useEffect(() => {
    function handlePointerDown(event: MouseEvent) {
      if (rootRef.current === null) {
        return
      }

      if (!rootRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }

    window.addEventListener('mousedown', handlePointerDown)

    return () => {
      window.removeEventListener('mousedown', handlePointerDown)
    }
  }, [])

  useEffect(() => {
    if (!isOpen || !openedWithKeyboard.current) return
    const selectedIndex = Math.max(
      0,
      options.findIndex((option) => option.value === value),
    )
    optionRefs.current[selectedIndex]?.focus()
    openedWithKeyboard.current = false
  }, [isOpen, options, value])

  useEffect(() => {
    if (!isOpen && optionRefs.current.some((option) => option === document.activeElement)) {
      controlRef.current?.focus()
    }
  }, [isOpen])

  function toggleDropdown() {
    openedWithKeyboard.current = false
    setIsOpen((current) => !current)
  }

  function moveOptionFocus(currentIndex: number, movement: number) {
    if (!options.length) return
    const nextIndex = (currentIndex + movement + options.length) % options.length
    optionRefs.current[nextIndex]?.focus()
  }

  function handleOptionKeyDown(event: KeyboardEvent<HTMLButtonElement>, index: number) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      moveOptionFocus(index, event.key === 'ArrowDown' ? 1 : -1)
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault()
      optionRefs.current[event.key === 'Home' ? 0 : options.length - 1]?.focus()
    } else if (event.key === 'Escape') {
      event.preventDefault()
      setIsOpen(false)
      controlRef.current?.focus()
    } else if (event.key === 'Tab') {
      setIsOpen(false)
    }
  }

  function handleOptionClick(event: ReactMouseEvent<HTMLButtonElement>, optionValue: string) {
    event.preventDefault()
    onChange?.(optionValue)
    setIsOpen(false)
    // Restore focus to the trigger after selecting an option via keyboard.
    if (event.detail === 0) controlRef.current?.focus()
  }

  function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
    if (
      event.key === 'ArrowDown' ||
      event.key === 'ArrowUp' ||
      event.key === 'Home' ||
      event.key === 'End'
    ) {
      event.preventDefault()
      if (!options.length) return
      if (!isOpen) {
        openedWithKeyboard.current = true
        setIsOpen(true)
      } else {
        optionRefs.current[event.key === 'End' ? options.length - 1 : 0]?.focus()
      }
      return
    }
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      openedWithKeyboard.current = !isOpen
      setIsOpen((current) => !current)
      return
    }

    if (event.key === 'Escape') {
      setIsOpen(false)
    }
  }

  return (
    <div
      className={`veratag-input-select${hasError ? ' veratag-input-select--error' : ''}`}
      ref={rootRef}
    >
      {label ? (
        <label className="veratag-input-select__label" htmlFor={id} id={`${id}-label`}>
          {label}
        </label>
      ) : null}

      <div className="veratag-input-select__wrapper">
        <button
          ref={controlRef}
          aria-controls={`${id}-listbox`}
          aria-describedby={describedBy}
          aria-expanded={isOpen}
          aria-haspopup="listbox"
          aria-invalid={hasError}
          className="veratag-input-select__control"
          id={id}
          onClick={toggleDropdown}
          onKeyDown={handleKeyDown}
          type="button"
        >
          <span className="veratag-input-select__content">
            {selectedOption ? renderOptionIcon(selectedOption) : null}

            <span
              className={
                selectedOption ? 'veratag-input-select__value' : 'veratag-input-select__placeholder'
              }
            >
              {selectedOption ? selectedOption.label : placeholder}
            </span>
          </span>

          <span
            className={`veratag-input-select__arrow${isOpen ? ' veratag-input-select__arrow--open' : ''}`}
          >
            <img
              alt=""
              aria-hidden="true"
              className="veratag-input-select__arrow-icon"
              src={config.formIcons.selectChevron}
            />
          </span>
        </button>

        <div
          aria-hidden={!isOpen}
          className={`veratag-input-select__dropdown${isOpen ? ' veratag-input-select__dropdown--open' : ''}`}
        >
          <div
            aria-labelledby={label ? `${id}-label` : undefined}
            className="veratag-input-select__options"
            id={`${id}-listbox`}
            role="listbox"
          >
            {options.map((option, index) => (
              <button
                aria-selected={option.value === value}
                ref={(element) => {
                  optionRefs.current[index] = element
                }}
                tabIndex={isOpen ? 0 : -1}
                onKeyDown={(event) => handleOptionKeyDown(event, index)}
                className={`veratag-input-select__option${option.value === value ? ' veratag-input-select__option--selected' : ''}`}
                key={option.value}
                onClick={(event) => handleOptionClick(event, option.value)}
                role="option"
                type="button"
              >
                <span className="veratag-input-select__option-content">
                  {renderOptionIcon(option)}

                  <span className="veratag-input-select__option-label">{option.label}</span>
                </span>
              </button>
            ))}
          </div>
        </div>
      </div>

      {hasError ? (
        <p className="veratag-input-select__error" id={errorId} role="alert">
          {errorText}
        </p>
      ) : null}

      {!hasError && hintText ? (
        <p className="veratag-input-select__hint" id={hintId}>
          {hintText}
        </p>
      ) : null}
    </div>
  )
}

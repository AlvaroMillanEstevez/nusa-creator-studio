import { describe, expect, it } from 'vitest'

import { sanitizeApiErrorMessage } from '@/services/api'

describe('API error sanitization', () => {
  it('uses fixed messages for authentication and access statuses', () => {
    expect(sanitizeApiErrorMessage(401, { message: 'Unauthenticated.' })).toBe(
      'Please log in to continue.',
    )
    expect(sanitizeApiErrorMessage(403, { message: 'Forbidden.' })).toBe(
      'You do not have access to this resource.',
    )
    expect(sanitizeApiErrorMessage(404, { message: 'Not Found.' })).toBe(
      'This resource could not be found.',
    )
    expect(sanitizeApiErrorMessage(419, { message: 'CSRF token mismatch.' })).toBe(
      'Your session expired. Please try again.',
    )
  })

  it('never exposes SQLSTATE or database query details', () => {
    const message = sanitizeApiErrorMessage(500, {
      message:
        'SQLSTATE[23505]: Unique violation: insert into "users" ("email") values (?) returning "id"',
    })

    expect(message).toBe('Something went wrong. Please try again.')
    expect(message).not.toContain('SQLSTATE')
    expect(message).not.toContain('insert into')
  })

  it('never exposes internal classes, vendor paths or exception locations', () => {
    for (const internalMessage of [
      'App\\Models\\User failed in /var/www/vendor/laravel/framework/src/File.php:42',
      'Illuminate\\Database\\QueryException in UserRepository.php on line 18',
      'Stack trace: #0 /var/www/app/Services/AuthService.php(10)',
    ]) {
      expect(sanitizeApiErrorMessage(422, { message: internalMessage })).toBe(
        'Please check the form and try again.',
      )
    }
  })

  it('preserves the first safe validation error', () => {
    expect(
      sanitizeApiErrorMessage(422, {
        message: 'The given data was invalid.',
        errors: {
          email: ['The email has already been taken.'],
          password: ['The password must be at least 8 characters.'],
        },
      }),
    ).toBe('The email has already been taken.')
  })

  it('preserves safe domain errors and their API handling', () => {
    expect(
      sanitizeApiErrorMessage(402, {
        message: 'You do not have enough platform credits to generate this campaign.',
      }),
    ).toBe('You do not have enough platform credits to generate this campaign.')
  })
})

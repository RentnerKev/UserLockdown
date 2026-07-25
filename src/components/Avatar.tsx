import { generateUrl } from '@nextcloud/router'
import { useState } from 'react'

import type { RestrictedUser } from '../types/user'

type AvatarProps = {
  user: RestrictedUser
  size?: 'small' | 'large'
}

const initialsFor = (displayName: string): string => {
  const initials = displayName
    .split(/\s+/u)
    .filter((part) => part.length > 0)
    .slice(0, 2)
    .map((part) => part.at(0)?.toLocaleUpperCase() ?? '')
    .join('')

  return initials || '?'
}

export const Avatar = ({ user, size = 'large' }: AvatarProps) => {
  const [imageFailed, setImageFailed] = useState(false)
  const pixels = size === 'large' ? 48 : 36
  const avatarUrl =
    user.avatarUrl ??
    generateUrl('/avatar/{userId}/{size}', {
      userId: user.id,
      size: pixels * 2,
    })

  if (imageFailed) {
    return (
      <span className={`user-lockdown-avatar user-lockdown-avatar--${size}`} aria-hidden="true">
        {initialsFor(user.displayName)}
      </span>
    )
  }

  return (
    <img
      className={`user-lockdown-avatar user-lockdown-avatar--${size}`}
      src={avatarUrl}
      alt=""
      width={pixels}
      height={pixels}
      onError={() => setImageFailed(true)}
    />
  )
}

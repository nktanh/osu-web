###
#    Copyright 2015-2017 ppy Pty. Ltd.
#
#    This file is part of osu!web. osu!web is distributed with the hope of
#    attracting more community contributions to the core ecosystem of osu!.
#
#    osu!web is free software: you can redistribute it and/or modify
#    it under the terms of the Affero GNU General Public License version 3
#    as published by the Free Software Foundation.
#
#    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
#    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
#    See the GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
###

{a, div, span} = React.DOM
el = React.createElement

bn = 'profile-header-extra'

rowValue = (value) ->
  "<strong>#{value}</strong>"

class ProfilePage.HeaderExtra extends React.Component
  render: =>
    originKeys = []
    originKeys.push 'country' if @props.user.country.name?
    originKeys.push 'age' if @props.user.age?

    playsWith =
      (@props.user.playstyle || []).map (s) ->
        osu.trans "common.device.#{s}"
      .join ', '

    div
      className:
        """
          osu-page osu-page--small osu-page--users-show-header-extra
          js-switchable-mode-page--scrollspy js-switchable-mode-page--page
        """
      'data-page-id': 'main'
      div className: bn,
        div className: "#{bn}__column #{bn}__column--text",
          if originKeys.length != 0 || @props.user.title?
            div className: "#{bn}__rows",
              if originKeys.length != 0
                div
                  className: "#{bn}__row",
                  dangerouslySetInnerHTML:
                    __html:
                      osu.trans "users.show.origin_#{originKeys.join('_')}",
                        country: rowValue @props.user.country.name
                        age: rowValue osu.trans('users.show.age', age: @props.user.age)

          div className: "#{bn}__rows",
            div
              className: "#{bn}__row"
              dangerouslySetInnerHTML:
                __html: @props.user.joinDate
            div
              className: "#{bn}__row"
              dangerouslySetInnerHTML:
                __html:
                  osu.trans 'users.show.lastvisit',
                    date: rowValue osu.timeago(@props.user.lastvisit)

          if @props.user.playstyle?
            div className: "#{bn}__rows",
              div
                className: "#{bn}__row"
                dangerouslySetInnerHTML:
                  __html:
                    osu.trans 'users.show.plays_with',
                      devices: rowValue playsWith

        div className: "#{bn}__column #{bn}__column--text",
          div className: "#{bn}__rows",
            @fancyLink
              key: 'location'
              icon: 'map-marker'
              title:
                osu.trans 'users.show.current_location',
                  location: @props.user.location

            @fancyLink
              key: 'interests'
              icon: 'heart-o'

          div className: "#{bn}__rows",
            @fancyLink
              key: 'twitter'
              url: "https://twitter.com/#{@props.user.twitter}"
              text:
                span null,
                  span
                    style: fontStyle: 'normal'
                    '@'
                  @props.user.twitter

            @fancyLink
              key: 'skype'
              url: "skype:#{@props.user.skype}?chat"

            @fancyLink
              key: 'lastfm'
              url: "https://last.fm/user/#{@props.user.lastfm}"

        div
          className: "#{bn}__column #{bn}__column--chart"
          div className: "#{bn}__rank-box",
            if @props.stats.rank.isRanked
              div null,
                div className: "#{bn}__rank-global",
                  "##{Math.round(@props.stats.rank.global).toLocaleString()}"
                div className: "#{bn}__rank-country",
                  "#{@props.user.country.name} ##{Math.round(@props.stats.rank.country).toLocaleString()}"

          div className: "#{bn}__rank-box",
            "#{Math.round(@props.stats.pp).toLocaleString()}pp"

  fancyLink: ({key, url, icon, text, title}) =>
    return if !@props.user[key]?

    component = if url? then a else span

    component
      href: url
      className: "#{bn}__row #{bn}__row--fancy-link"
      title: title
      el Icon,
        name: icon ? key
        modifiers: ['fw']
        parentClass: "#{bn}__fancy-link-icon"
      text ? @props.user[key]

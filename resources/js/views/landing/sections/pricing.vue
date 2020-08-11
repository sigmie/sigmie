<template>
  <section style="background-color: #f8fafc" class="bg-white md:py-8">
    <div class="container mx-auto px-4">
      <div class="flex flex-col-reverse md:flex-col">
        <div class="flex flex-row flex-wrap-reverse justify-between w-10/12 md:w-full mx-auto md:mx-0 mt-8 md:mt-0 md:pt-0 pt-8 border-t-2 border-b-0 md:border-t-0 md:border-b-2 border-gray-200">
          <div class="flex mx-auto md:mx-0 py-5">
            <h2 class="font-bold text-gray-800 text-3xl">
              Starting at $12 a month
              <span
                class="text-gray-400 pl-2 font-normal text-sm"
              >/ per project.</span>
            </h2>
          </div>
          <div class="flex mx-auto md:mx-0">
            <inertia-link
              :href="$route('register')"
              class="bg-theme-secondary-lighter w-60 md:w-48 flex items-center justify-center px-4 rounded-lg h-12 self-center text-sm leading-5 text-white"
            >Get started</inertia-link>
          </div>
        </div>
        <div class="flex-col md:flex-row flex md:space-x-4 justify-between pt-8">
          <div class="w-4/5 md:w-1/3 mx-auto flex-1 md:mx-0">
            <span class="uppercase h-8 text-cool-gray-300 mb-3 text-sm">Simple pricing plan</span>
            <h3 class="font-bold text-gray-800 text-3xl mb-4">All-in-on platform</h3>
            <p
              class="font-normal text-base text-cool-gray-400"
            >Lorem ipsum dolor sit amet consectetur adipisicing elit. Perferendis iure quibusdam, obcaecati officia vel atque consequatur doloremque adipisci ipsum.</p>
          </div>
          <div class="w-4/5 md:w-1/3 mx-auto flex-1 md:mx-0 mt-8 md:mt-0">
            <ul class="text-black">
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Comments</h4>
                  <p class="text-gray-400 text-sm">
                    Somebody help me to go to holidays, i am sweating like a idiot here in
                    the office, and i have no idea what i could do to feel better.
                  </p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Alerts</h4>
                  <p class="text-gray-400 text-sm">
                    Get notified when someones posts i was having a bad day yesterday the
                    then the night falled and i got myself killed a comment on a posting.
                  </p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Proxy</h4>
                  <p class="text-gray-400 text-sm">
                    Get notified lore mispu dolores how go about when
                    someones posts a comment on a posting.
                  </p>
                </div>
              </li>
            </ul>
          </div>
          <div class="w-4/5 md:w-1/3 mx-auto flex-1 md:mx-0">
            <ul class="text-black">
              <li class="text-sm leading-5 mb-4 flex flex-">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Cloud platform</h4>
                  <p class="text-gray-400 text-sm">
                    If this at any time goes live i am gona get crazy. Because it feels like
                    i am not making any progress. Event though i am fixing every day some bugs
                    and making some improvenments.
                  </p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Customer support</h4>
                  <p class="text-gray-400 text-sm">
                    I think that the custormer support job will really be easy since,
                    i won't have any customers any soon.
                  </p>
                </div>
              </li>
              <li class="text-sm leading-5 mb-4 flex">
                <div>
                  <icon-check class="w-6 text-green-500 mr-3"></icon-check>
                </div>
                <div>
                  <h4 class="font-semibold text-base text-gray-700 mb-1">Blah</h4>
                  <p
                    class="text-gray-400 text-sm"
                  >I have no idea about what i should write in this last bullet point.</p>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
export default {};
</script>

<style scoped>
</style>